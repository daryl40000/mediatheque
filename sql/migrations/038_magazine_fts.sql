-- Phase M5 (v0.4.1) — Recherche FTS5 numéros et sujets magazines

CREATE VIRTUAL TABLE IF NOT EXISTS magazine_issue_fts USING fts5(
    oeuvre_id UNINDEXED,
    series_id UNINDEXED,
    numero,
    sommaire,
    pdf_text_preview,
    date_parution,
    tokenize='unicode61 remove_diacritics 2'
);

CREATE VIRTUAL TABLE IF NOT EXISTS magazine_subject_fts USING fts5(
    subject_id UNINDEXED,
    category UNINDEXED,
    label,
    detail,
    tokenize='unicode61 remove_diacritics 2'
);

INSERT INTO magazine_issue_fts (oeuvre_id, series_id, numero, sommaire, pdf_text_preview, date_parution)
SELECT om.oeuvre_id, om.series_id, COALESCE(om.numero, ''), COALESCE(om.sommaire, ''), COALESCE(om.pdf_text_preview, ''), COALESCE(om.date_parution, '')
FROM oeuvre_magazine om;

INSERT INTO magazine_subject_fts (subject_id, category, label, detail)
SELECT id, category, label, COALESCE(detail, '')
FROM magazine_subject;

CREATE TRIGGER IF NOT EXISTS trg_magazine_issue_fts_ai AFTER INSERT ON oeuvre_magazine BEGIN INSERT INTO magazine_issue_fts (oeuvre_id, series_id, numero, sommaire, pdf_text_preview, date_parution) VALUES (NEW.oeuvre_id, NEW.series_id, COALESCE(NEW.numero, ''), COALESCE(NEW.sommaire, ''), COALESCE(NEW.pdf_text_preview, ''), COALESCE(NEW.date_parution, '')); END;

CREATE TRIGGER IF NOT EXISTS trg_magazine_issue_fts_au AFTER UPDATE ON oeuvre_magazine BEGIN DELETE FROM magazine_issue_fts WHERE oeuvre_id = OLD.oeuvre_id; INSERT INTO magazine_issue_fts (oeuvre_id, series_id, numero, sommaire, pdf_text_preview, date_parution) VALUES (NEW.oeuvre_id, NEW.series_id, COALESCE(NEW.numero, ''), COALESCE(NEW.sommaire, ''), COALESCE(NEW.pdf_text_preview, ''), COALESCE(NEW.date_parution, '')); END;

CREATE TRIGGER IF NOT EXISTS trg_magazine_issue_fts_ad AFTER DELETE ON oeuvre_magazine BEGIN DELETE FROM magazine_issue_fts WHERE oeuvre_id = OLD.oeuvre_id; END;

CREATE TRIGGER IF NOT EXISTS trg_magazine_subject_fts_ai AFTER INSERT ON magazine_subject BEGIN INSERT INTO magazine_subject_fts (subject_id, category, label, detail) VALUES (NEW.id, NEW.category, NEW.label, COALESCE(NEW.detail, '')); END;

CREATE TRIGGER IF NOT EXISTS trg_magazine_subject_fts_au AFTER UPDATE ON magazine_subject BEGIN DELETE FROM magazine_subject_fts WHERE subject_id = OLD.id; INSERT INTO magazine_subject_fts (subject_id, category, label, detail) VALUES (NEW.id, NEW.category, NEW.label, COALESCE(NEW.detail, '')); END;

CREATE TRIGGER IF NOT EXISTS trg_magazine_subject_fts_ad AFTER DELETE ON magazine_subject BEGIN DELETE FROM magazine_subject_fts WHERE subject_id = OLD.id; END;
