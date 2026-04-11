ALTER TABLE posts
    ADD COLUMN inline_image_1 VARCHAR(255) DEFAULT NULL AFTER cover_image,
    ADD COLUMN inline_image_2 VARCHAR(255) DEFAULT NULL AFTER inline_image_1,
    ADD COLUMN inline_image_3 VARCHAR(255) DEFAULT NULL AFTER inline_image_2,
    ADD COLUMN inline_image_4 VARCHAR(255) DEFAULT NULL AFTER inline_image_3,
    ADD COLUMN inline_image_5 VARCHAR(255) DEFAULT NULL AFTER inline_image_4,
    ADD COLUMN inline_image_6 VARCHAR(255) DEFAULT NULL AFTER inline_image_5;
