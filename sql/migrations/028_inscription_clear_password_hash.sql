-- Ne plus conserver de hash de mot de passe sur les demandes terminées (audit / sauvegarde).

UPDATE inscription_requests
SET password_hash = ''
WHERE status IN ('approved', 'rejected') AND TRIM(password_hash) != '';
