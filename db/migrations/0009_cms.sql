-- CMS: editable content blocks, structured pages, media library, team members,
-- and blog posts (so existing blog URLs survive cutover via 301s — C-V7-10).

-- Keyed content blocks rendered into bespoke pages (hero copy, hours, sections).
CREATE TABLE content_blocks (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    block_key    VARCHAR(120) NOT NULL,                   -- e.g. "home.hero.heading"
    value_html   LONGTEXT NULL,                           -- sanitized against an allowlist on render
    updated_by   BIGINT UNSIGNED NULL,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_block_key (block_key),
    CONSTRAINT fk_block_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standalone CMS pages (+ per-page SEO).
CREATE TABLE pages (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug         VARCHAR(160) NOT NULL,
    type         ENUM('page','post') NOT NULL DEFAULT 'page',
    title        VARCHAR(200) NOT NULL,
    body_html    LONGTEXT NULL,
    excerpt      VARCHAR(500) NULL,
    hero_image   VARCHAR(255) NULL,
    seo_title    VARCHAR(200) NULL,
    seo_description VARCHAR(320) NULL,
    status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_page_slug (slug),
    KEY idx_page_type_status (type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Media library (uploads stored out of web root; finfo-checked, random names).
CREATE TABLE media (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    filename     VARCHAR(255) NOT NULL,                   -- random-hex stored name
    original_name VARCHAR(255) NULL,
    mime         VARCHAR(100) NOT NULL,
    width        INT NULL,
    height       INT NULL,
    alt_text     VARCHAR(300) NULL,
    bytes        BIGINT NOT NULL DEFAULT 0,
    uploaded_by  BIGINT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_media_created (created_at),
    CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE team_members (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(160) NOT NULL,
    role_title   VARCHAR(160) NULL,
    bio          TEXT NULL,
    photo_path   VARCHAR(255) NULL,
    sort         INT NOT NULL DEFAULT 0,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
