-- Caisse multi-modes (GAP-26) : ventilation et clôture par mode de paiement

-- Ajout colonnes mode-spécifiques sur clôtures (idempotent)
ALTER TABLE daily_closures
    ADD COLUMN IF NOT EXISTS counted_cash_fcfa            INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS counted_mobile_money_fcfa    INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS counted_card_fcfa            INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS counted_bank_transfer_fcfa   INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS counted_voucher_fcfa         INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS expected_cash_fcfa           INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS expected_mobile_money_fcfa   INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS expected_card_fcfa           INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS expected_bank_transfer_fcfa  INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS expected_voucher_fcfa        INT UNSIGNED NULL;

-- S'assurer que la colonne payment_method de sales accepte tous les modes
ALTER TABLE sales
    MODIFY COLUMN payment_method ENUM('especes','mobile_money','carte','virement','voucher','autre','mobile') NOT NULL DEFAULT 'especes';
