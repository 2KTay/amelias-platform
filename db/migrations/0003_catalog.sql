-- Catalog: categories, products, variants, modifiers, dietary tags, inventory.
-- Products carry tax_category (AZ TPT treatment), DTC flags (wine), and a
-- day-part availability window (browse anytime, order in-window only — C-V7-8).

CREATE TABLE categories (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(120) NOT NULL,
    name        VARCHAR(160) NOT NULL,
    kind        ENUM('food','market','giftcard','catering','wine','other') NOT NULL DEFAULT 'food',
    description VARCHAR(500) NULL,
    sort        INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cat_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(160) NOT NULL,
    name            VARCHAR(200) NOT NULL,
    description     TEXT NULL,
    type            ENUM('food','market','giftcard','catering','wine','event','subscription') NOT NULL DEFAULT 'food',
    -- AZ TPT treatment bucket (per-category tax; gift cards never taxed at sale).
    tax_category    ENUM('prepared_food','retail_goods','grocery','service','non_taxable') NOT NULL DEFAULT 'prepared_food',
    price_cents     BIGINT NOT NULL DEFAULT 0,
    image_path      VARCHAR(255) NULL,
    -- finite-stock items track inventory; infinite (made-to-order) do not.
    tracks_inventory TINYINT(1) NOT NULL DEFAULT 0,
    is_86           TINYINT(1) NOT NULL DEFAULT 0,        -- sold-out toggle -> hidden/blocked on public menu
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    -- wine / alcohol DTC (gated off until Q#1/#2 — see product_shippable_states)
    ships_dtc       TINYINT(1) NOT NULL DEFAULT 0,
    is_alcohol      TINYINT(1) NOT NULL DEFAULT 0,
    -- day-part gating (NULL window = always orderable when active)
    day_part        ENUM('all_day','day','pm','happy_hour','catering') NOT NULL DEFAULT 'all_day',
    available_from  TIME NULL,
    available_to    TIME NULL,
    sort            INT NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prod_slug (slug),
    KEY idx_prod_type (type),
    KEY idx_prod_daypart (day_part)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hidden many-to-many: product <-> category (join table per convention).
CREATE TABLE product_category_map (
    product_id  BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, category_id),
    CONSTRAINT fk_pcm_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pcm_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_variants (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id  BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(160) NOT NULL,                    -- e.g. "Serves 12"
    price_cents BIGINT NOT NULL DEFAULT 0,
    sort        INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_variant_product (product_id),
    CONSTRAINT fk_variant_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE modifier_groups (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(160) NOT NULL,                    -- e.g. "Add protein"
    min_select  INT NOT NULL DEFAULT 0,                   -- >=1 => required
    max_select  INT NOT NULL DEFAULT 1,
    sort        INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE modifiers (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id      BIGINT UNSIGNED NOT NULL,
    name          VARCHAR(160) NOT NULL,
    price_delta_cents BIGINT NOT NULL DEFAULT 0,
    sort          INT NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_mod_group (group_id),
    CONSTRAINT fk_mod_group FOREIGN KEY (group_id) REFERENCES modifier_groups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_modifier_map (
    product_id BIGINT UNSIGNED NOT NULL,
    group_id   BIGINT UNSIGNED NOT NULL,
    sort       INT NOT NULL DEFAULT 0,
    PRIMARY KEY (product_id, group_id),
    CONSTRAINT fk_pmm_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pmm_group FOREIGN KEY (group_id) REFERENCES modifier_groups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dietary_tags (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug      VARCHAR(60) NOT NULL,
    label     VARCHAR(80) NOT NULL,
    is_allergen TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_diet_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_dietary_map (
    product_id BIGINT UNSIGNED NOT NULL,
    tag_id     BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, tag_id),
    CONSTRAINT fk_pdm_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_pdm_tag FOREIGN KEY (tag_id) REFERENCES dietary_tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- On-hand stock for finite items. Reservable = stock - SUM(active holds).
CREATE TABLE inventory (
    product_id      BIGINT UNSIGNED NOT NULL,
    stock           INT NOT NULL DEFAULT 0,
    low_stock_threshold INT NOT NULL DEFAULT 0,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id),
    CONSTRAINT fk_inv_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
