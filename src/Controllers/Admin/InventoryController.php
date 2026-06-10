<?php

declare(strict_types=1);

namespace Amelias\Controllers\Admin;

use Amelias\Database\Database;
use Amelias\Http\Request;

/**
 * Inventory (screen 23, 6.4), Manager + Owner.
 *
 * Stock levels, low-stock threshold highlight, adjust counts, and an 86 toggle
 * that flips products.is_86 (propagating to the public menu). The "reservable"
 * column is on-hand minus the SUM of active inventory_holds, computed live,
 * never stored. All mutations are CSRF-guarded POSTs (PRG back to the list).
 */
final class InventoryController extends AdminController
{
    public function index(Request $request): void
    {
        $user = $this->requireRole('owner', 'manager');

        if ($request->isPost()) {
            if (!csrf_verify((string) $request->input('_csrf'))) {
                $this->redirect(url('/admin/inventory'));
                return;
            }
            $action    = (string) $request->input('action');
            $productId = (int) $request->input('product_id');
            if ($productId > 0) {
                match ($action) {
                    'adjust'  => $this->adjustStock($productId, (int) $request->input('delta'), (int) $user['id']),
                    'set'     => $this->setStock($productId, (int) $request->input('stock'), (int) $request->input('threshold'), (int) $user['id']),
                    'toggle86' => $this->toggle86($productId, (int) $user['id']),
                    default   => null,
                };
            }
            $this->redirect(url('/admin/inventory'));
            return;
        }

        $items = $this->items();
        $stats = [
            'out'     => 0,
            'low'     => 0,
            'tracked' => count($items),
            'holds'   => 0,
        ];
        foreach ($items as $it) {
            $stats['holds'] += $it['reserved'];
            if ($it['is_86'] || $it['reservable'] <= 0) {
                $stats['out']++;
            } elseif ($it['reservable'] <= $it['low_stock_threshold']) {
                $stats['low']++;
            }
        }

        $this->viewAdmin('admin/inventory', [
            'title' => 'Inventory',
            'styles' => ['pages/admin-depth.css'],
            'items' => $items,
            'stats' => $stats,
        ]);
    }

    /**
     * Tracked products with on-hand, active holds, and reservable (= on-hand −
     * SUM active holds).
     *
     * @return list<array<string,mixed>>
     */
    private function items(): array
    {
        $rows = Database::fetchAll(
            "SELECT p.id, p.name, p.is_86,
                    i.stock, i.low_stock_threshold,
                    COALESCE((
                        SELECT SUM(h.quantity) FROM inventory_holds h
                         WHERE h.product_id = p.id AND h.status = 'active'
                    ), 0) AS reserved
               FROM products p
               JOIN inventory i ON i.product_id = p.id
              WHERE p.tracks_inventory = 1
              ORDER BY p.name ASC"
        );

        return array_map(static function (array $r): array {
            $stock    = (int) $r['stock'];
            $reserved = (int) $r['reserved'];
            return [
                'id'                  => (int) $r['id'],
                'name'                => (string) $r['name'],
                'is_86'               => (bool) $r['is_86'],
                'stock'               => $stock,
                'reserved'            => $reserved,
                'reservable'          => $stock - $reserved,
                'low_stock_threshold' => (int) $r['low_stock_threshold'],
            ];
        }, $rows);
    }

    private function adjustStock(int $productId, int $delta, int $userId): void
    {
        if ($delta === 0) {
            return;
        }
        // Clamp at zero; never let on-hand go negative.
        Database::run(
            'UPDATE inventory SET stock = GREATEST(0, stock + ?) WHERE product_id = ?',
            [$delta, $productId]
        );
        $this->audit($userId, 'inventory.adjust', $productId, ['delta' => $delta]);
    }

    private function setStock(int $productId, int $stock, int $threshold, int $userId): void
    {
        Database::run(
            'UPDATE inventory SET stock = ?, low_stock_threshold = ? WHERE product_id = ?',
            [max(0, $stock), max(0, $threshold), $productId]
        );
        $this->audit($userId, 'inventory.set', $productId, ['stock' => $stock, 'threshold' => $threshold]);
    }

    private function toggle86(int $productId, int $userId): void
    {
        Database::run('UPDATE products SET is_86 = 1 - is_86 WHERE id = ?', [$productId]);
        $now = (int) Database::fetchValue('SELECT is_86 FROM products WHERE id = ?', [$productId]);
        $this->audit($userId, 'inventory.toggle86', $productId, ['is_86' => $now]);
    }

    private function audit(int $userId, string $action, int $productId, array $after): void
    {
        Database::run(
            'INSERT INTO audit_log (actor_type, actor_id, action, entity_type, entity_id, after_json)
             VALUES (?, ?, ?, ?, ?, ?)',
            ['user', $userId, $action, 'product', (string) $productId, json_encode($after, JSON_THROW_ON_ERROR)]
        );
    }
}
