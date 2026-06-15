-- Cleanup paramètres inutiles (jamais utilisés par le code) : 
-- supprimés après audit du 2026-05-06.

DELETE FROM app_settings WHERE setting_key IN (
    'caisse.usd_rate',
    'caisse.eur_rate',
    'caisse.allow_multi_currency',
    'caisse.currency',
    'billetterie.qr_secret_rotation',
    'billetterie.max_seats_per_sale',
    'print.pdf_engine',
    'audit.level',
    'rh.payslip_period',
    'rh.leave_days_annual'
);
