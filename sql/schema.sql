-- ============================================================
-- Lumen Blog — Database Schema
-- Import this file first (phpMyAdmin > Import, or `mysql -u user -p db < schema.sql`)
-- ============================================================

-- ------------------------------------------------------------
-- Table: user
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,          -- bcrypt hash, never plain text
    role       ENUM('author', 'admin') NOT NULL DEFAULT 'author',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: blogPost
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blogPost (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(200) NOT NULL,
    slug       VARCHAR(220) NOT NULL UNIQUE,
    content    MEDIUMTEXT   NOT NULL,          -- raw Markdown
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_blogpost_user
        FOREIGN KEY (user_id) REFERENCES user(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_blogpost_created ON blogPost (created_at DESC);
CREATE INDEX idx_blogpost_user ON blogPost (user_id);

-- No seed users are inserted here on purpose: passwords must go through
-- PHP's password_hash() so they're salted correctly. Create your first
-- account from the Register page once the app is running instead.
