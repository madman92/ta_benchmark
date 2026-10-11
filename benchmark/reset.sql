-- Identical fixture, including timestamps and initial counters, for every trial.
\set ON_ERROR_STOP on
BEGIN;
TRUNCATE post_views, posts, users;
INSERT INTO users
SELECT i, 'User ' || i, 'user' || i || '@bench.test'
FROM generate_series(1, 100) AS i;
INSERT INTO posts
SELECT i, ((i - 1) % 100) + 1, 'Post title ' || i,
       'Body content for post ' || i || '. Lorem ipsum dolor sit amet.',
       0, TIMESTAMP '2000-01-01' + i * INTERVAL '1 second'
FROM generate_series(1, 1000) AS i;
INSERT INTO post_views
SELECT i, i, '0.0.0.0', TIMESTAMP '2000-01-01'
FROM generate_series(1, 1000) AS i;
COMMIT;
ANALYZE users;
ANALYZE posts;
ANALYZE post_views;
