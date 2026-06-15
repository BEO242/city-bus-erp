-- Migration : agrandir bus_lines.code à VARCHAR(20) et tariffs.travel_class si besoin
ALTER TABLE bus_lines MODIFY code VARCHAR(20) NOT NULL;
