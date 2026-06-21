USE carfliphq;

ALTER TABLE expenses
  ADD COLUMN receipt_file VARCHAR(255) NULL AFTER expense_date;

ALTER TABLE tasks
  ADD COLUMN task_photo VARCHAR(255) NULL AFTER status;
