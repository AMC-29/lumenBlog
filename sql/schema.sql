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
    bio        VARCHAR(500) NOT NULL DEFAULT '',
    avatar     VARCHAR(37) NULL,
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
    status     ENUM('draft', 'published') NOT NULL DEFAULT 'published',
    published_at DATETIME NULL,
    cover_image VARCHAR(37) NULL,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_blogpost_user
        FOREIGN KEY (user_id) REFERENCES user(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_blogpost_created ON blogPost (created_at DESC);
CREATE INDEX idx_blogpost_user ON blogPost (user_id);
CREATE INDEX idx_blogpost_status_published ON blogPost (status, published_at);
CREATE INDEX idx_blogpost_popular ON blogPost (status, view_count, id);

CREATE TABLE IF NOT EXISTS tag (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS post_tag (
    post_id INT UNSIGNED NOT NULL,
    tag_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    CONSTRAINT fk_post_tag_post FOREIGN KEY (post_id) REFERENCES blogPost(id) ON DELETE CASCADE,
    CONSTRAINT fk_post_tag_tag FOREIGN KEY (tag_id) REFERENCES tag(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS slug_history (
    post_id INT UNSIGNED NOT NULL,
    slug    VARCHAR(220) NOT NULL UNIQUE,
    PRIMARY KEY (post_id, slug),
    CONSTRAINT fk_slug_history_post FOREIGN KEY (post_id) REFERENCES blogPost(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comment (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    status ENUM('visible', 'hidden') NOT NULL DEFAULT 'visible',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comment_post FOREIGN KEY (post_id) REFERENCES blogPost(id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
    INDEX idx_comment_post_created (post_id, created_at, id),
    INDEX idx_comment_user_created (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comment_report (
    comment_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comment_id, user_id),
    CONSTRAINT fk_comment_report_comment FOREIGN KEY (comment_id) REFERENCES comment(id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_report_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS post_like (
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (post_id, user_id),
    INDEX idx_post_like_user (user_id),
    CONSTRAINT fk_post_like_post FOREIGN KEY (post_id) REFERENCES blogPost(id) ON DELETE CASCADE,
    CONSTRAINT fk_post_like_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookmark (
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (post_id, user_id),
    INDEX idx_bookmark_user (user_id),
    CONSTRAINT fk_bookmark_post FOREIGN KEY (post_id) REFERENCES blogPost(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookmark_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `follow` (
    follower_id INT UNSIGNED NOT NULL,
    author_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, author_id),
    INDEX idx_follow_author (author_id),
    CONSTRAINT fk_follow_follower FOREIGN KEY (follower_id) REFERENCES user(id) ON DELETE CASCADE,
    CONSTRAINT fk_follow_author FOREIGN KEY (author_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- No seed users are inserted here on purpose: passwords must go through
-- PHP's password_hash() so they're salted correctly. Create your first
-- account from the Register page once the app is running instead.
