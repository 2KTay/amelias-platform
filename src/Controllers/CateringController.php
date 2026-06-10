<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\Auth;
use Amelias\Services\Notifications\Notifier;
use Amelias\Support\Session;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Catering & events (screen 11, Task 4.3).
 *
 * Three offerings, all funneling into one enquiry the admin prices:
 *   1. Party Platters by Amelia's, order 48 hrs ahead, pickup in store.
 *   2. Boutique Catering by EAT , full-service inquiry (no fixed date rule).
 *   3. Meal Delivery by EAT      , order by Thursday 8am for Monday delivery.
 *
 * GET renders the offerings, the platter catalog (products.type='catering' with
 * serves-12/24 variants), and the inquiry form. POST validates lead time per
 * offering and creates a catering_requests row (status 'new'). The admin later
 * quotes it; acceptance spawns a deposit order then a balance order
 * (orders.kind catering_deposit | catering_balance), that pricing/acceptance
 * is admin-side and out of scope for the public page.
 *
 * Money is integer cents; UTC stored / Phoenix displayed; CSRF on POST.
 */
final class CateringController extends Controller
{
    /** Party platters need at least this much lead time before the event. */
    private const PLATTER_LEAD_HOURS = 48;

    public function index(Request $request): void
    {
        if ($request->isPost()) {
            $this->submit($request);
            return;
        }

        // Platter catalog: catering products with their serves-12/24 variants.
        $products = Database::fetchAll(
            "SELECT p.id, p.slug, p.name, p.description, p.price_cents,
                    c.name AS category, c.sort AS cat_sort
               FROM products p
               LEFT JOIN product_category_map pcm ON pcm.product_id = p.id
               LEFT JOIN categories c ON c.id = pcm.category_id AND c.is_active = 1
              WHERE p.is_active = 1 AND p.type = 'catering'
              ORDER BY c.sort, c.name, p.sort, p.name"
        );
        $variants = Database::fetchAll(
            "SELECT pv.product_id, pv.name, pv.price_cents
               FROM product_variants pv
               JOIN products p ON p.id = pv.product_id
              WHERE pv.is_active = 1 AND p.type = 'catering'
              ORDER BY pv.sort"
        );
        $variantsByProduct = [];
        foreach ($variants as $v) {
            $variantsByProduct[(int) $v['product_id']][] = $v;
        }

        $grouped = [];
        foreach ($products as $p) {
            $p['variants'] = $variantsByProduct[(int) $p['id']] ?? [];
            $grouped[$p['category'] ?? 'Platters'][] = $p;
        }

        $this->viewPublic('public/catering', [
            'title'         => 'Catering & Events',
            'bodyClass'     => 'has-grain',
            'styles'        => ['pages/catering.css'],
            'platterGroups' => $grouped,
            'platterLead'   => self::PLATTER_LEAD_HOURS,
            'flash'         => $this->flash(),
        ]);
    }

    /** Validate + persist a catering enquiry, then notify the kitchen. */
    private function submit(Request $request): void
    {
        if (!csrf_verify((string) $request->input('_csrf'))) {
            $this->fail('Your session expired. Please try again.');
            return;
        }

        $offering = $this->normalizeOffering((string) $request->input('offering', ''));
        $name  = trim((string) $request->input('name', ''));
        $email = trim((string) $request->input('email', ''));
        $phone = trim((string) $request->input('phone', ''));
        $dateStr = trim((string) $request->input('event_date', ''));
        $headcount = (int) $request->input('headcount', 0);
        $details = trim((string) $request->input('message', ''));
        $eventType = trim((string) $request->input('event_type', ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('Please give us your name and a valid email so we can reply.');
            return;
        }

        // Rate-limit enquiries per IP to blunt form spam.
        if (!Auth::rateLimit('catering:' . $request->ip(), 6, 600)) {
            $this->fail('Too many requests. Please wait a moment and try again.');
            return;
        }

        $eventDate = $this->parseDate($dateStr);

        // Lead-time validation: party platters need 48 hrs; meal delivery is
        // order-by-Thursday-8am for Monday delivery.
        $leadError = $this->checkLeadTime($offering, $eventDate);
        if ($leadError !== null) {
            $this->fail($leadError);
            return;
        }

        // Tie to an existing/created customer record (no account required).
        $customerId = null;
        if ($email !== '') {
            $customer = Auth::findOrCreateCustomer($email, [
                'first_name' => $name,
                'phone'      => $phone !== '' ? $phone : null,
            ]);
            $customerId = (int) ($customer['id'] ?? 0) ?: null;
        }
        // Prefer a logged-in customer if present.
        $sessionCustomer = Session::customer();
        if ($sessionCustomer !== null) {
            $customerId = (int) $sessionCustomer['id'];
        }

        $detailParts = [];
        if ($eventType !== '') {
            $detailParts[] = 'Event type: ' . $eventType;
        }
        if ($details !== '') {
            $detailParts[] = $details;
        }
        $detailText = implode("\n\n", $detailParts);

        $requestId = Database::insert(
            'INSERT INTO catering_requests
                (customer_id, offering, event_date, headcount, details, status,
                 contact_email, contact_phone, contact_name)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $customerId,
                $offering,
                $eventDate?->format('Y-m-d'),
                $headcount > 0 ? $headcount : null,
                $detailText !== '' ? $detailText : null,
                'new',
                $email,
                $phone !== '' ? $phone : null,
                $name,
            ]
        );

        // Notify the kitchen inbox (best-effort; failure must not block the
        // customer's confirmation).
        try {
            (new Notifier())->notify(
                'catering_request',
                (string) $requestId,
                'catering-inquiry',
                ['email' => (string) (config('catering_inbox') ?: 'info@ameliasaz.com')],
                [
                    'subject'    => 'New catering inquiry, ' . $this->offeringLabel($offering),
                    'offering'   => $this->offeringLabel($offering),
                    'name'       => $name,
                    'email'      => $email,
                    'phone'      => $phone,
                    'eventDate'  => $eventDate?->format('l, F j, Y') ?? 'Flexible',
                    'headcount'  => $headcount > 0 ? (string) $headcount : 'TBD',
                    'details'    => $detailText,
                ],
                'email'
            );
        } catch (\Throwable $e) {
            error_log('[catering] notify failed: ' . $e->getMessage());
        }

        $_SESSION['_flash'] = [
            'type'    => 'success',
            'message' => "Thanks, {$name}, your catering inquiry is in. We'll be in touch shortly to build your menu and pricing. "
                . 'A deposit secures your date once we send a quote.',
        ];
        $this->redirect(url('/catering'));
    }

    /** Map the posted offering value to the enum, defaulting to party platters. */
    private function normalizeOffering(string $value): string
    {
        return match ($value) {
            'boutique', 'boutique_catering'      => 'boutique',
            'meal_delivery', 'meal'              => 'meal_delivery',
            default                              => 'party_platters',
        };
    }

    private function offeringLabel(string $offering): string
    {
        return match ($offering) {
            'boutique'      => 'Boutique Catering by EAT',
            'meal_delivery' => 'Meal Delivery by EAT',
            default         => "Party Platters by Amelia's",
        };
    }

    /** Parse a YYYY-MM-DD date string (Phoenix wall-clock) or null. */
    private function parseDate(string $dateStr): ?DateTimeImmutable
    {
        if ($dateStr === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($dateStr . ' 00:00:00', new DateTimeZone(FMT_TZ));
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Enforce per-offering lead time. Returns an error message string when the
     * requested date is too soon, or null when acceptable.
     */
    private function checkLeadTime(string $offering, ?DateTimeImmutable $eventDate): ?string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone(FMT_TZ));

        if ($offering === 'party_platters') {
            if ($eventDate === null) {
                return 'Please choose your event date, party platters need at least 48 hours\' notice.';
            }
            $earliest = $now->modify('+' . self::PLATTER_LEAD_HOURS . ' hours');
            // Compare against end of the requested day so a same-time-of-day
            // pickup 2 days out still qualifies.
            $eventEnd = $eventDate->setTime(23, 59, 59);
            if ($eventEnd < $earliest) {
                return 'Party platters need at least 48 hours\' lead time. Please pick a later date or call us at (602) 499-5195.';
            }
            return null;
        }

        if ($offering === 'meal_delivery') {
            // Order by Thursday 8am for Monday delivery: the chosen delivery
            // date must be at least the next Monday after this Thursday cutoff.
            if ($eventDate === null) {
                return null; // delivery date can be arranged; no hard block.
            }
            if ($eventDate < $now->setTime(0, 0, 0)) {
                return 'Please choose an upcoming delivery date. Orders are placed by Thursday 8am for the following Monday.';
            }
            return null;
        }

        // Boutique full-service: no hard lead-time block (handled by quote).
        if ($eventDate !== null && $eventDate < $now->setTime(0, 0, 0)) {
            return 'Please choose an upcoming event date.';
        }
        return null;
    }

    private function fail(string $message): void
    {
        $_SESSION['_flash'] = ['type' => 'error', 'message' => $message];
        $this->redirect(url('/catering'));
    }

    private function flash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return is_array($flash) ? $flash : null;
    }
}
