-- Create quiz_results table to store assessment history
CREATE TABLE IF NOT EXISTS `quiz_results` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `module_id` INT NOT NULL,
    `quiz_id` INT NOT NULL,
    `score` INT NOT NULL,
    `completion_date` DATETIME NOT NULL,
    `module_title` VARCHAR(255) NOT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;