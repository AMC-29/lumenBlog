-- Run once against an existing Lumen Blog database.
-- Explicitly assigning updated_at to itself prevents the ON UPDATE column
-- from changing while existing posts receive their initial published_at.

ALTER TABLE user
    ADD COLUMN bio VARCHAR(500) NOT NULL DEFAULT '',
    ADD COLUMN avatar VARCHAR(37) NULL;

ALTER TABLE blogPost
    ADD COLUMN status ENUM('draft', 'published') NOT NULL DEFAULT 'published',
    ADD COLUMN published_at DATETIME NULL,
    ADD COLUMN cover_image VARCHAR(37) NULL,
    ADD INDEX idx_blogpost_status_published (status, published_at);

UPDATE blogPost
SET published_at = created_at, updated_at = updated_at
WHERE status = 'published';

CREATE TABLE tag (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE post_tag (
    post_id INT UNSIGNED NOT NULL,
    tag_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    CONSTRAINT fk_post_tag_post FOREIGN KEY (post_id) REFERENCES blogPost(id) ON DELETE CASCADE,
    CONSTRAINT fk_post_tag_tag FOREIGN KEY (tag_id) REFERENCES tag(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE slug_history (
    post_id INT UNSIGNED NOT NULL,
    slug    VARCHAR(220) NOT NULL UNIQUE,
    PRIMARY KEY (post_id, slug),
    CONSTRAINT fk_slug_history_post FOREIGN KEY (post_id) REFERENCES blogPost(id) ON DELETE CASCADE
) ENGINE=InnoDB;
