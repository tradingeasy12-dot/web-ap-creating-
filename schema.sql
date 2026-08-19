-- ============================================================
-- Video Platform — MySQL Schema (PHP backend version)
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS admin_users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(30) NOT NULL DEFAULT 'super_admin',
    is_active     TINYINT(1) DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100) NOT NULL,
    slug             VARCHAR(120) NOT NULL UNIQUE,
    parent_id        INT NULL,
    meta_title       VARCHAR(160),
    meta_description VARCHAR(320),
    sort_order       INT DEFAULT 0,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS tags (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE,
    slug VARCHAR(80) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS storage_providers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    provider     VARCHAR(50) NOT NULL,
    label        VARCHAR(100),
    access_key   VARCHAR(255) NOT NULL,
    secret_key   VARCHAR(255) NULL,
    bucket_name  VARCHAR(150) NOT NULL,
    region       VARCHAR(50),
    endpoint_url VARCHAR(255) NULL,
    cdn_url      VARCHAR(255) NULL,
    notes        TEXT NULL,
    is_active    TINYINT(1) DEFAULT 0,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ad_library (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    type       VARCHAR(30) NOT NULL DEFAULT 'video_vast',
    ad_code    TEXT NOT NULL,
    status     VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ad_slots (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    slot_key           VARCHAR(60) NOT NULL UNIQUE,
    label              VARCHAR(100) NOT NULL,
    is_enabled         TINYINT(1) DEFAULT 1,
    ad_library_id      INT NULL,
    custom_ad_code     TEXT NULL,
    skip_after_seconds INT NULL,
    frequency          VARCHAR(30) NULL,
    trigger_at         VARCHAR(30) NULL,
    placement          VARCHAR(50) NULL,
    FOREIGN KEY (ad_library_id) REFERENCES ad_library(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS videos (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    title               VARCHAR(200) NOT NULL,
    slug                VARCHAR(220) NOT NULL UNIQUE,
    description         TEXT,
    upload_type         VARCHAR(20) NOT NULL DEFAULT 'self_hosted',
    embed_code          TEXT NULL,
    storage_provider_id INT NULL,
    storage_path        VARCHAR(500) NULL,
    thumbnail_path      VARCHAR(500) NULL,
    duration_seconds    INT NULL,
    category_id         INT NULL,
    status              VARCHAR(20) DEFAULT 'draft',
    ads_enabled         TINYINT(1) DEFAULT 1,
    preroll_ad_id       INT NULL,
    views_count         BIGINT DEFAULT 0,
    likes_count         BIGINT DEFAULT 0,
    dislikes_count      BIGINT DEFAULT 0,
    meta_title          VARCHAR(160),
    meta_description    VARCHAR(320),
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (storage_provider_id) REFERENCES storage_providers(id) ON DELETE SET NULL,
    FOREIGN KEY (preroll_ad_id) REFERENCES ad_library(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS video_tags (
    video_id INT NOT NULL,
    tag_id   INT NOT NULL,
    PRIMARY KEY (video_id, tag_id),
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS age_gate_settings (
    id                INT PRIMARY KEY DEFAULT 1,
    is_enabled        TINYINT(1) DEFAULT 1,
    heading           VARCHAR(200) DEFAULT 'This site contains adult content',
    body_text         TEXT,
    button_text       VARCHAR(100) DEFAULT 'I am 18 or older',
    exit_redirect_url VARCHAR(255) DEFAULT 'https://google.com',
    remember_duration VARCHAR(30) DEFAULT '1_day'
);

CREATE TABLE IF NOT EXISTS footer_pages (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    button_text   VARCHAR(100) NOT NULL,
    popup_content TEXT NOT NULL,
    is_visible    TINYINT(1) DEFAULT 1,
    sort_order    INT DEFAULT 0,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value TEXT
);

CREATE TABLE IF NOT EXISTS video_views (
    id           BIGINT AUTO_INCREMENT PRIMARY KEY,
    video_id     INT NOT NULL,
    viewed_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    country_code CHAR(2) NULL,
    ip_hash      VARCHAR(64) NULL,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    INDEX idx_video_time (video_id, viewed_at),
    INDEX idx_country (country_code)
);

-- Daily rollup of video_views, written by cron/rollup-traffic.php. This is what powers
-- the "today's traffic" column in Admin -> All Videos without counting raw rows live.
CREATE TABLE IF NOT EXISTS traffic_daily (
    id           BIGINT AUTO_INCREMENT PRIMARY KEY,
    stat_date    DATE NOT NULL,
    video_id     INT NULL,          -- NULL row = site-wide total for that day
    country_code CHAR(2) NULL,
    views        BIGINT DEFAULT 0,
    UNIQUE KEY uniq_day_video_country (stat_date, video_id, country_code),
    INDEX idx_stat_date (stat_date),
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;

-- Seed sensible defaults so the panel isn't empty on first login
INSERT INTO age_gate_settings (id, is_enabled, heading, body_text, button_text, exit_redirect_url)
VALUES (1, 1, 'This site contains adult content',
        'You must be 18 years or older to enter. By continuing you confirm you meet the legal age requirement in your location.',
        'I am 18 or older', 'https://google.com')
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO ad_slots (slot_key, label, is_enabled, skip_after_seconds, frequency, placement) VALUES
('preroll', 'Pre-roll', 1, 5, 'every_video', NULL),
('midroll', 'Mid-roll', 0, NULL, NULL, NULL),
('postroll', 'Post-roll', 0, NULL, NULL, NULL),
('homepage_banner', 'Homepage banner', 1, NULL, NULL, 'top'),
('sidebar_banner', 'Sidebar banner', 0, NULL, NULL, 'sidebar'),
('content_middle_banner', 'Middle bar (in-content)', 0, NULL, NULL, 'middle'),
('footer_banner', 'Footer bar', 0, NULL, NULL, 'footer'),
('popunder', 'Popunder / Direct Link', 0, NULL, 'once_per_session', NULL)
ON DUPLICATE KEY UPDATE slot_key = slot_key;

INSERT INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'My Video Site'),
('tagline', 'Watch and share'),
('meta_title_template', '{video_title} — {site_name}')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO categories (name, slug) VALUES
('Category A', 'category-a'), ('Category B', 'category-b'), ('Category C', 'category-c')
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO footer_pages (button_text, popup_content, sort_order) VALUES
('About', 'We are an independent platform focused on giving creators a fair, well-moderated place to publish their work.', 1),
('Terms', 'By using this site you agree to our community guidelines and content policies.', 2),
('Privacy', 'We collect only the data needed to run the platform and verify age where legally required.', 3),
('DMCA', 'If you believe content on this site infringes your copyright, contact our designated agent.', 4),
('Support', 'Need help with your account or a technical issue? Contact our support team.', 5),
('Content removal', 'To request removal of content, submit proof of identity and a link to the content.', 6)
ON DUPLICATE KEY UPDATE button_text = button_text;
