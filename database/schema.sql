-- GameVerse: schema for PostgreSQL
-- This schema is a preparation for the future server-side database.
CREATE TABLE IF NOT EXISTS genres (
  genre_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  description TEXT
);
CREATE TABLE IF NOT EXISTS games (
  game_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  genre_id BIGINT REFERENCES genres(genre_id) ON DELETE SET NULL,
  release_year SMALLINT CHECK (release_year BETWEEN 1950 AND 2100),
  platforms VARCHAR(300) NOT NULL DEFAULT '',
  rating NUMERIC(2,1) CHECK (rating >= 0 AND rating <= 5),
  tag VARCHAR(80),
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS news (
  news_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  author_name VARCHAR(120) NOT NULL DEFAULT 'Редакция GameVerse',
  status VARCHAR(20) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','published','archived')),
  published_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_games_genre ON games(genre_id);
CREATE INDEX IF NOT EXISTS idx_news_status_date ON news(status, published_at DESC);
