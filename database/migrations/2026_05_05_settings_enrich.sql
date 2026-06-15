-- ============================================================
-- Enrichissement module Paramètres
-- Nouvelles catégories + settings manquants
-- ============================================================

-- Nouvelles catégories : voyage, impression, rh, sms, integration
INSERT IGNORE INTO app_settings
    (setting_key, category, setting_type, setting_value, label, description, sort_order)
VALUES
-- ─── VOYAGE ───────────────────────────────────────────────────────────────
('voyage.checkin_open_minutes',   'voyage', 'int',    '60',    'Ouverture embarquement (min avant départ)',  'Durée en minutes avant le départ où l\'embarquement s\'ouvre', 10),
('voyage.checkin_close_minutes',  'voyage', 'int',    '10',    'Fermeture embarquement (min avant départ)', 'Délai minimal avant départ pour fermer l\'embarquement', 11),
('voyage.allow_overbooking',      'voyage', 'bool',   '0',     'Autoriser surbooking',                      'Si activé, le nombre de tickets peut dépasser la capacité du bus', 12),
('voyage.overbooking_pct',        'voyage', 'int',    '10',    'Taux de surbooking (%)',                    'Pourcentage maximal de surbooking autorisé (ex: 10 = +10%)', 13),
('voyage.auto_close_minutes',     'voyage', 'int',    '30',    'Clôture automatique (min après arrivée)',   '0 = désactivé', 14),
('voyage.incident_notify_admin',  'voyage', 'bool',   '1',     'Notifier admin sur incident voyage',        '', 15),
('voyage.min_driver_rest_hours',  'voyage', 'int',    '8',     'Repos minimal chauffeur entre voyages (h)', '', 16),

-- ─── IMPRESSION / BILLETS ─────────────────────────────────────────────────
('print.receipt_copies',          'impression', 'int',    '1',    'Copies reçu caisse',                        '1 = original uniquement', 20),
('print.ticket_logo_enabled',     'impression', 'bool',   '1',    'Afficher logo sur billets',                 '', 21),
('print.ticket_footer_text',      'impression', 'text',   'Merci de voyager avec City Bus. Conservez ce billet jusqu\'à destination.',
                                                                   'Pied de page billet PDF',                   '', 22),
('print.preprint_watermark',      'impression', 'bool',   '0',    'Filigrane sur pré-imprimés',                '', 23),
('print.qr_size',                 'impression', 'int',    '200',  'Taille QR (pixels)',                        'Taille du code QR sur le billet PDF', 24),
('print.pdf_engine',              'impression', 'string', 'mpdf', 'Moteur PDF',                               'mpdf (installé)', 25),

-- ─── RH ───────────────────────────────────────────────────────────────────
('rh.payslip_period',             'rh', 'string', 'monthly', 'Périodicité fiche de paie',                    'monthly / biweekly', 30),
('rh.leave_days_annual',          'rh', 'int',    '30',      'Jours de congé annuels',                       '', 31),
('rh.cnss_rate_employee',         'rh', 'int',    '4',       'Cotisation CNSS salarié (%)',                  '', 32),
('rh.cnss_rate_employer',         'rh', 'int',    '16',      'Cotisation CNSS employeur (%)',                '', 33),
('rh.irpp_enabled',               'rh', 'bool',   '0',       'Activer calcul IRPP',                          '', 34),
('rh.overtime_rate_multiplier',   'rh', 'string', '1.5',     'Coefficient heures supplémentaires',           '', 35),
('rh.medical_cert_alert_days',    'rh', 'int',    '30',      'Alerte expiration visite médicale (jours)',    '', 36),
('rh.license_alert_days',         'rh', 'int',    '45',      'Alerte expiration permis (jours)',             '', 37),

-- ─── SMS ──────────────────────────────────────────────────────────────────
('sms.enabled',                   'sms', 'bool',   '0',    'SMS actifs',                                     '', 40),
('sms.provider',                  'sms', 'string', '',     'Fournisseur SMS',                                'Ex: twilio, orange, africas_talking', 41),
('sms.api_key',                   'sms', 'secret', '',     'API Key SMS',                                    '', 42),
('sms.sender_id',                 'sms', 'string', 'CityBus', 'Identifiant émetteur SMS',                   'Max 11 caractères', 43),
('sms.notify_ticket_sold',        'sms', 'bool',   '0',    'SMS à la vente d\'un billet',                   '', 44),
('sms.notify_trip_departure',     'sms', 'bool',   '0',    'SMS rappel départ (1h avant)',                  '', 45),
('sms.notify_trip_delay',         'sms', 'bool',   '0',    'SMS en cas de retard voyage',                   '', 46),

-- ─── INTÉGRATION ──────────────────────────────────────────────────────────
('integration.webhook_url',       'integration', 'string', '',  'Webhook sortant (URL)',                     'POST JSON à chaque événement métier', 50),
('integration.webhook_secret',    'integration', 'secret', '',  'Webhook secret',                            'Signature HMAC-SHA256', 51),
('integration.webhook_events',    'integration', 'text',   '',  'Événements webhook',                        'Un par ligne : ticket.sold, trip.closed, ...', 52),
('integration.api_enabled',       'integration', 'bool',   '0', 'API REST activée',                         '', 53),
('integration.api_key',           'integration', 'secret', '',  'API Key REST',                              '', 54),

-- ─── Compléments BILLETTERIE ──────────────────────────────────────────────
('billetterie.allow_seat_choice',     'billetterie', 'bool', '1',  'Choix de siège par le passager',             '', 203),
('billetterie.max_seats_per_sale',    'billetterie', 'int',  '10', 'Maximum de billets par transaction',         '', 204),
('billetterie.cancellation_delay_h',  'billetterie', 'int',  '2',  'Délai annulation billet (h avant départ)',   '0 = non autorisé', 205),
('billetterie.refund_pct',            'billetterie', 'int',  '80', 'Remboursement annulation (%)',               '', 206),
('billetterie.print_on_sale',         'billetterie', 'bool', '1',  'Imprimer automatiquement à la vente',        '', 207),

-- ─── Compléments CAISSE ───────────────────────────────────────────────────
('caisse.require_open_session',   'caisse', 'bool', '1',     'Exiger session ouverte pour vendre',             '', 303),
('caisse.session_max_hours',      'caisse', 'int',  '12',    'Durée max session caisse (h)',                   '', 304),
('caisse.auto_close',             'caisse', 'bool', '0',     'Clôture automatique à minuit',                   '', 305),
('caisse.allow_multi_currency',   'caisse', 'bool', '0',     'Accepter USD / EUR',                            '', 306),
('caisse.usd_rate',               'caisse', 'int',  '600',   'Taux USD → FCFA',                               '', 307),
('caisse.eur_rate',               'caisse', 'int',  '655',   'Taux EUR → FCFA',                               '', 308);
