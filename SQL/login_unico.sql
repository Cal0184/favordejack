-- Favor de Jack · Inicio de sesión único
-- Si ya ejecutaste este ALTER, no vuelvas a ejecutarlo.
ALTER TABLE Usuario ADD COLUMN PasswordHint VARCHAR(255) NULL AFTER Activo;
