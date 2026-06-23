USE carfliphq;

CREATE TABLE IF NOT EXISTS car_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  car_id INT NOT NULL,
  file_type ENUM('photo','document') DEFAULT 'photo',
  title VARCHAR(150) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS parts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  car_id INT NOT NULL,
  part_name VARCHAR(150) NOT NULL,
  supplier VARCHAR(150),
  cost DECIMAL(10,2) DEFAULT 0,
  status ENUM('Needed','Ordered','Arrived','Installed','Cancelled') DEFAULT 'Needed',
  ordered_date DATE,
  arrived_date DATE,
  installed_date DATE,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sale_listings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  car_id INT NOT NULL,
  platform VARCHAR(100),
  listing_price DECIMAL(10,2) DEFAULT 0,
  status ENUM('Draft','Listed','Offer Received','Deposit Taken','Sold','Withdrawn') DEFAULT 'Draft',
  listed_date DATE,
  buyer_name VARCHAR(150),
  buyer_contact VARCHAR(150),
  offer_amount DECIMAL(10,2) DEFAULT 0,
  deposit_amount DECIMAL(10,2) DEFAULT 0,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
);
