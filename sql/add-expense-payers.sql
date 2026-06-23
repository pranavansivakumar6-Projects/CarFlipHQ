USE carfliphq;

ALTER TABLE expenses
  ADD COLUMN paid_by VARCHAR(100) NULL AFTER amount;
