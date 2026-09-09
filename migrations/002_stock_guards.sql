-- Prevent invalid stock values even if a write bypasses the PHP forms.
ALTER TABLE products ADD CONSTRAINT chk_products_stock_nonnegative CHECK (stock_qty >= 0);
