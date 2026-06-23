USE carfliphq;

ALTER TABLE tasks
  ADD COLUMN hours_spent DECIMAL(8,2) DEFAULT 0 AFTER status;
