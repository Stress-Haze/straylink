CREATE TABLE lost_pet_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    pet_name VARCHAR(120) NOT NULL,
    species ENUM('dog', 'cat', 'other') NOT NULL,
    breed VARCHAR(120) DEFAULT NULL,
    gender ENUM('male', 'female', 'unknown') NOT NULL DEFAULT 'unknown',
    age_text VARCHAR(80) DEFAULT NULL,
    color_markings VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    contact_name VARCHAR(120) NOT NULL,
    contact_number VARCHAR(40) NOT NULL,
    contact_email VARCHAR(190) DEFAULT NULL,
    city VARCHAR(120) NOT NULL,
    last_seen_label VARCHAR(255) NOT NULL,
    last_seen_latitude DECIMAL(10,7) DEFAULT NULL,
    last_seen_longitude DECIMAL(10,7) DEFAULT NULL,
    last_seen_at DATETIME NOT NULL,
    reward_amount DECIMAL(10,2) DEFAULT NULL,
    reward_note VARCHAR(255) DEFAULT NULL,
    poster_image VARCHAR(255) NOT NULL,
    status ENUM('pending', 'active', 'sighted', 'found', 'closed', 'expired', 'rejected') NOT NULL DEFAULT 'pending',
    found_at DATETIME DEFAULT NULL,
    closed_at DATETIME DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    renewal_count INT NOT NULL DEFAULT 0,
    visibility ENUM('public', 'hidden') NOT NULL DEFAULT 'hidden',
    reunion_note TEXT DEFAULT NULL,
    admin_note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lost_pet_member (member_id),
    INDEX idx_lost_pet_status (status),
    INDEX idx_lost_pet_city (city),
    INDEX idx_lost_pet_species (species),
    INDEX idx_lost_pet_expires (expires_at),
    CONSTRAINT fk_lost_pet_member
        FOREIGN KEY (member_id) REFERENCES members(id)
        ON DELETE CASCADE
);

CREATE TABLE lost_pet_sightings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lost_pet_post_id INT UNSIGNED NOT NULL,
    reported_by_member_id INT UNSIGNED DEFAULT NULL,
    reporter_name VARCHAR(120) NOT NULL,
    reporter_contact VARCHAR(120) DEFAULT NULL,
    location_label VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,7) DEFAULT NULL,
    longitude DECIMAL(10,7) DEFAULT NULL,
    seen_at DATETIME NOT NULL,
    notes TEXT DEFAULT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    status ENUM('new', 'reviewed', 'useful', 'dismissed') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sighting_post (lost_pet_post_id),
    INDEX idx_sighting_status (status),
    CONSTRAINT fk_sighting_post
        FOREIGN KEY (lost_pet_post_id) REFERENCES lost_pet_posts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_sighting_member
        FOREIGN KEY (reported_by_member_id) REFERENCES members(id)
        ON DELETE SET NULL
);

CREATE TABLE lost_pet_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lost_pet_post_id INT UNSIGNED NOT NULL,
    changed_by_member_id INT UNSIGNED DEFAULT NULL,
    old_status ENUM('pending', 'active', 'sighted', 'found', 'closed', 'expired', 'rejected') DEFAULT NULL,
    new_status ENUM('pending', 'active', 'sighted', 'found', 'closed', 'expired', 'rejected') NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_history_post (lost_pet_post_id),
    CONSTRAINT fk_status_history_post
        FOREIGN KEY (lost_pet_post_id) REFERENCES lost_pet_posts(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_status_history_member
        FOREIGN KEY (changed_by_member_id) REFERENCES members(id)
        ON DELETE SET NULL
);
