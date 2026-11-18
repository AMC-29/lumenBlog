-- Run once after migrate_features.sql on an existing Lumen database.

ALTER TABLE blogPost
    ADD COLUMN view_count INT UNSIGNED NOT NULL DEFAULT 0,
    ADD INDEX idx_blogpost_popular (status, view_count, id);

CREATE TABLE comment (
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

CREATE TABLE comment_report (
    comment_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comment_id, user_id),
    CONSTRAINT fk_comment_report_comment FOREIGN KEY (comment_id) REFERENCES comment(id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_report_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE post_like (
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (post_id, user_id),
    INDEX idx_post_like_user (user_id),
    CONSTRAINT fk_post_like_post FOREIGN KEY (post_id) REFERENCES blogPost(id) ON DELETE CASCADE,
    CONSTRAINT fk_post_like_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bookmark (
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (post_id, user_id),
    INDEX idx_bookmark_user (user_id),
    CONSTRAINT fk_bookmark_post FOREIGN KEY (post_id) REFERENCES blogPost(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookmark_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `follow` (
    follower_id INT UNSIGNED NOT NULL,
    author_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, author_id),
    INDEX idx_follow_author (author_id),
    CONSTRAINT fk_follow_follower FOREIGN KEY (follower_id) REFERENCES user(id) ON DELETE CASCADE,
    CONSTRAINT fk_follow_author FOREIGN KEY (author_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;
