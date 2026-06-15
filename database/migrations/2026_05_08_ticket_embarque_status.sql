-- Ajout du statut 'embarque' pour les tickets (validation à l'embarquement, distinct de 'valide' à la destination)
ALTER TABLE tickets MODIFY COLUMN status
    ENUM('emis','embarque','valide','arrive','annule') NOT NULL DEFAULT 'emis';

-- Idem pour billets bagages
ALTER TABLE baggage_tickets MODIFY COLUMN status
    ENUM('emis','embarque','valide','arrive','annule') NOT NULL DEFAULT 'emis';
