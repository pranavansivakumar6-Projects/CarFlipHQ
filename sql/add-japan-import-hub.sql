USE carfliphq;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS can_view_imports TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS can_manage_imports TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS can_view_import_finance TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS import_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value DECIMAL(12,4) NOT NULL DEFAULT 0,
  notes VARCHAR(255),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS import_assessments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  import_ref VARCHAR(30) NOT NULL UNIQUE,
  make VARCHAR(100) NOT NULL,
  model VARCHAR(100) NOT NULL,
  variant VARCHAR(100),
  year INT,
  chassis_vin VARCHAR(120),
  mileage INT,
  auction_house VARCHAR(150),
  auction_date DATE,
  auction_grade VARCHAR(30),
  interior_grade VARCHAR(30),
  lot_number VARCHAR(80),
  japan_agent VARCHAR(120),
  status ENUM('Draft','Approved to Bid','Won','Shipped','Arrived','Compliance','Ready for Sale','Closed') DEFAULT 'Draft',
  exchange_rate DECIMAL(12,4) DEFAULT 0,
  hammer_price_jpy DECIMAL(14,2) DEFAULT 0,
  auction_fee_jpy DECIMAL(14,2) DEFAULT 0,
  japan_agent_fee_jpy DECIMAL(14,2) DEFAULT 0,
  inland_transport_jpy DECIMAL(14,2) DEFAULT 0,
  export_docs_jpy DECIMAL(14,2) DEFAULT 0,
  japan_port_fees_jpy DECIMAL(14,2) DEFAULT 0,
  other_japan_costs_jpy DECIMAL(14,2) DEFAULT 0,
  other_japan_costs_notes VARCHAR(255),
  expected_sale_price_aud DECIMAL(12,2) DEFAULT 0,
  target_profit_aud DECIMAL(12,2) DEFAULT 0,
  ocean_freight_aud DECIMAL(12,2) DEFAULT 3000,
  marine_insurance_aud DECIMAL(12,2) DEFAULT 200,
  port_charges_aud DECIMAL(12,2) DEFAULT 800,
  customs_broker_aud DECIMAL(12,2) DEFAULT 350,
  biosecurity_aud DECIMAL(12,2) DEFAULT 300,
  port_transport_aud DECIMAL(12,2) DEFAULT 300,
  compliance_aud DECIMAL(12,2) DEFAULT 3000,
  registration_aud DECIMAL(12,2) DEFAULT 230,
  duty_rate DECIMAL(7,4) DEFAULT 0,
  duty_manual_aud DECIMAL(12,2) DEFAULT 0,
  gst_rate DECIMAL(7,4) DEFAULT 0.10,
  other_australia_costs_aud DECIMAL(12,2) DEFAULT 0,
  other_australia_costs_notes VARCHAR(255),
  calculation_snapshot LONGTEXT,
  calculation_version VARCHAR(30) DEFAULT 'jp-import-v1',
  notes TEXT,
  created_by INT,
  updated_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS import_user_access (
  assessment_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (assessment_id, user_id),
  FOREIGN KEY (assessment_id) REFERENCES import_assessments(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS import_audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT NOT NULL,
  user_id INT,
  action VARCHAR(80) NOT NULL,
  details TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (assessment_id) REFERENCES import_assessments(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT IGNORE INTO import_settings (setting_key, setting_value, notes) VALUES
('ocean_freight_aud', 3000, 'Default ocean freight allowance'),
('marine_insurance_aud', 200, 'Default marine insurance allowance'),
('port_charges_aud', 800, 'Default Australian port and terminal allowance'),
('customs_broker_aud', 350, 'Default customs broker fee'),
('biosecurity_aud', 300, 'Default biosecurity cleaning allowance'),
('port_transport_aud', 300, 'Default port to workshop transport'),
('compliance_aud', 3000, 'Default compliance allowance'),
('registration_aud', 230, 'Default three-month registration allowance'),
('gst_rate', 0.10, 'GST estimate rate'),
('duty_rate', 0, 'Import duty estimate rate'),
('minimum_profit_aud', 2000, 'Warning threshold for expected profit');
