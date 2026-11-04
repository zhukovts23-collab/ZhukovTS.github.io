CREATE TABLE IF NOT EXISTS steam_pulse_sources(app_id INT PRIMARY KEY,players INT NULL,sampled_at DATETIME NULL,attempted_at DATETIME NULL) CHARACTER SET utf8mb4;
CREATE TABLE IF NOT EXISTS steam_pulse_samples(app_id INT NOT NULL,sampled_at DATETIME NOT NULL,players INT NOT NULL,PRIMARY KEY(app_id,sampled_at)) CHARACTER SET utf8mb4;
