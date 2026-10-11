-- Dedicated benchmark databases only. Applied once before the application starts.
\set ON_ERROR_STOP on
DROP TABLE IF EXISTS post_views, posts, users CASCADE;
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE
);
CREATE TABLE posts (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    views_count INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL
);
CREATE INDEX posts_user_created ON posts(user_id, created_at DESC, id DESC);
CREATE TABLE post_views (
    id INTEGER PRIMARY KEY,
    post_id INTEGER NOT NULL REFERENCES posts(id),
    ip_address VARCHAR(45) NOT NULL,
    viewed_at TIMESTAMP NOT NULL
);
CREATE INDEX post_views_post ON post_views(post_id);
CREATE INDEX post_views_recent ON post_views(viewed_at DESC, id DESC);
