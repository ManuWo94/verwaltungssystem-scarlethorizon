#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import sys
import json
import re
import argparse
from typing import Dict, List, Optional, Union
import traceback

import requests
from bs4 import BeautifulSoup
from PyPDF2 import PdfReader

class DocumentParser:
    """Klasse zum Parsen verschiedener Dokumenttypen für das Justizsystem."""
    
    def __init__(self):
        """Initialisiert den DocumentParser."""
        pass

    def _parse_amount_field(self, value: str):
        """Zerlegt einen Betrag in Einzel-, Mindest- und Höchstwert."""
        amount = amount_min = amount_max = 0.0
        if not value:
            return amount, amount_min, amount_max

        normalized = value.replace('€', '').replace('$', '')
        range_match = re.search(r'(\d+(?:[.,]\d+)?)\s*(?:-|–|bis|to|bis zu)\s*(\d+(?:[.,]\d+)?)', normalized, flags=re.IGNORECASE)
        if range_match:
            amount_min = float(range_match.group(1).replace(',', '.'))
            amount_max = float(range_match.group(2).replace(',', '.'))
            amount = amount_max or amount_min
            return amount, amount_min, amount_max

        single_match = re.search(r'(\d+(?:[.,]\d+)?)', normalized)
        if single_match:
            amount = amount_min = amount_max = float(single_match.group(1).replace(',', '.'))

        return amount, amount_min, amount_max

    def _parse_community_service(self, value: str) -> int:
        """Extrahiert Strafarbeitsstunden aus einem Textfragment."""
        if not value:
            return 0

        match = re.search(r'(\d+(?:[.,]\d+)?)\s*(?:stunden|std|h)', value, flags=re.IGNORECASE)
        if match:
            try:
                return int(float(match.group(1).replace(',', '.')))
            except ValueError:
                return 0
        return 0
    
    def parse_pdf(self, file_path: str) -> Dict:
        """
        Extrahiert Text und Metadaten aus einer PDF-Datei.
        
        Args:
            file_path: Pfad zur PDF-Datei
            
        Returns:
            Ein Dictionary mit dem extrahierten Text und Metadaten
        """
        try:
            pdf_reader = PdfReader(file_path)
            
            text_content = ""
            for i, page in enumerate(pdf_reader.pages):
                text_content += page.extract_text() + "\n\n"
            
            # Metadaten extrahieren
            metadata = {}
            if pdf_reader.metadata:
                for key, value in pdf_reader.metadata.items():
                    if key.startswith('/'):
                        key = key[1:]  # Entferne führendes '/'
                    metadata[key] = str(value)
            
            # Anzahl der Seiten
            page_count = len(pdf_reader.pages)
            
            return {
                'success': True,
                'content': text_content,
                'metadata': metadata,
                'page_count': page_count,
                'type': 'pdf'
            }
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
                'traceback': traceback.format_exc()
            }
    
    def parse_html(self, file_path: str, encoding: str = 'utf-8') -> Dict:
        """
        Extrahiert Text und Metadaten aus einer HTML-Datei.
        
        Args:
            file_path: Pfad zur HTML-Datei
            encoding: Zeichenkodierung der Datei
            
        Returns:
            Ein Dictionary mit dem extrahierten Text und Metadaten
        """
        try:
            with open(file_path, 'r', encoding=encoding) as file:
                html_content = file.read()
            
            soup = BeautifulSoup(html_content, 'html.parser')
            
            # Metadaten extrahieren
            metadata = {}
            
            # Titel
            if soup.title:
                metadata['title'] = soup.title.string
            
            # Meta-Tags
            for meta in soup.find_all('meta'):
                if meta.get('name'):
                    metadata[meta.get('name')] = meta.get('content')
                elif meta.get('property'):
                    metadata[meta.get('property')] = meta.get('content')
            
            # Haupttext extrahieren (entfernt Skripte, Stile und andere unwichtige Elemente)
            for script in soup(["script", "style", "nav", "footer", "header"]):
                script.extract()
            
            text_content = soup.get_text(separator='\n', strip=True)
            
            # Entferne mehrfache Leerzeilen
            text_content = re.sub(r'\n\s*\n', '\n\n', text_content)
            
            return {
                'success': True,
                'content': text_content,
                'metadata': metadata,
                'type': 'html'
            }
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
                'traceback': traceback.format_exc()
            }
    
    def parse_url(self, url: str) -> Dict:
        """
        Lädt eine Webseite und extrahiert Text und Metadaten.
        
        Args:
            url: URL der Webseite
            
        Returns:
            Ein Dictionary mit dem extrahierten Text und Metadaten
        """
        try:
            response = requests.get(url, timeout=10)
            response.raise_for_status()  # Löst eine Exception aus, wenn der Request nicht erfolgreich war
            
            # Encoding ermitteln
            if response.encoding is None:
                response.encoding = 'utf-8'
            
            soup = BeautifulSoup(response.text, 'html.parser')
            
            # Metadaten extrahieren
            metadata = {
                'url': url,
                'status_code': response.status_code
            }
            
            # Titel
            if soup.title:
                metadata['title'] = soup.title.string
            
            # Meta-Tags
            for meta in soup.find_all('meta'):
                if meta.get('name'):
                    metadata[meta.get('name')] = meta.get('content')
                elif meta.get('property'):
                    metadata[meta.get('property')] = meta.get('content')
            
            # Haupttext extrahieren (entfernt Skripte, Stile und andere unwichtige Elemente)
            for script in soup(["script", "style", "nav", "footer", "header"]):
                script.extract()
            
            text_content = soup.get_text(separator='\n', strip=True)
            
            # Entferne mehrfache Leerzeilen
            text_content = re.sub(r'\n\s*\n', '\n\n', text_content)
            
            return {
                'success': True,
                'content': text_content,
                'metadata': metadata,
                'type': 'url'
            }
        except requests.exceptions.RequestException as e:
            return {
                'success': False,
                'error': str(e),
                'traceback': traceback.format_exc()
            }
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
                'traceback': traceback.format_exc()
            }
    
    def parse_docx(self, file_path: str) -> Dict:
        """
        Extrahiert Text und Metadaten aus einer DOCX-Datei.
        
        Args:
            file_path: Pfad zur DOCX-Datei
            
        Returns:
            Ein Dictionary mit dem extrahierten Text und Metadaten
        """
        try:
            # Da wir docx nicht direkt installiert haben, geben wir eine Fehlermeldung zurück
            return {
                'success': False,
                'error': 'DOCX-Parsing ist aktuell nicht unterstützt. Bitte installieren Sie die "python-docx" Bibliothek.',
                'type': 'docx'
            }
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
                'traceback': traceback.format_exc()
            }
    
    def parse_google_docs_url(self, url: str) -> Dict:
        """
        Versucht, eine Google Docs URL zu parsen, indem die Webansicht geladen wird.
        
        Args:
            url: Google Docs URL
            
        Returns:
            Ein Dictionary mit dem extrahierten Text und Metadaten
        """
        # Stellen Sie sicher, dass es sich um eine Google Docs URL handelt
        if "docs.google.com" not in url:
            return {
                'success': False,
                'error': 'Die URL scheint keine Google Docs URL zu sein.',
                'type': 'url'
            }
        
        # Füge "/pub" hinzu, wenn es nicht für die Webansicht formatiert ist
        if "/pub" not in url and "output=html" not in url:
            if "/edit" in url:
                url = url.replace("/edit", "/pub")
            elif url.endswith("/"):
                url = url + "pub"
            else:
                url = url + "/pub"
        
        # Versuchen Sie, die URL zu parsen
        return self.parse_url(url)
    
    def detect_and_parse(self, input_path: str) -> Dict:
        """
        Erkennt den Dateityp und wendet den entsprechenden Parser an.
        
        Args:
            input_path: Pfad zur Datei oder URL
            
        Returns:
            Ein Dictionary mit dem extrahierten Text und Metadaten
        """
        # Überprüfen, ob es sich um eine URL handelt
        if input_path.startswith('http://') or input_path.startswith('https://'):
            # Wenn es eine Google Docs URL ist
            if 'docs.google.com' in input_path:
                return self.parse_google_docs_url(input_path)
            # Andere URLs
            return self.parse_url(input_path)
        
        # Überprüfen, ob die Datei existiert
        if not os.path.exists(input_path):
            return {
                'success': False,
                'error': f'Die Datei {input_path} wurde nicht gefunden.'
            }
        
        # Dateityp anhand der Erweiterung erkennen
        _, ext = os.path.splitext(input_path)
        ext = ext.lower()
        
        if ext == '.pdf':
            return self.parse_pdf(input_path)
        elif ext in ['.html', '.htm']:
            return self.parse_html(input_path)
        elif ext in ['.docx']:
            return self.parse_docx(input_path)
        elif ext in ['.txt']:
            # Einfache Textdatei parsen
            try:
                with open(input_path, 'r', encoding='utf-8') as file:
                    content = file.read()
                return {
                    'success': True,
                    'content': content,
                    'metadata': {
                        'title': os.path.basename(input_path)
                    },
                    'type': 'text'
                }
            except Exception as e:
                return {
                    'success': False,
                    'error': str(e),
                    'traceback': traceback.format_exc()
                }
        else:
            return {
                'success': False,
                'error': f'Nicht unterstützter Dateityp: {ext}'
            }
    
    def extract_sections(self, text: str, section_pattern: Optional[str] = None) -> List[Dict]:
        """
        Extrahiert Abschnitte aus dem Text basierend auf einem Muster.
        
        Args:
            text: Der zu verarbeitende Text
            section_pattern: Ein regulärer Ausdruck, um Abschnitte zu erkennen
            
        Returns:
            Eine Liste von Dictionaries mit den erkannten Abschnitten
        """
        if not section_pattern:
            # Standardmuster für Abschnitte (z.B. "§ 1.", "Artikel 1.", "1.")
            section_pattern = r'(?:§|Artikel|Art\.|)\s*(\d+)[\.:]'
        
        sections = []
        current_section = None
        
        for line in text.split('\n'):
            match = re.match(section_pattern, line.strip())
            if match:
                if current_section:
                    sections.append(current_section)
                
                section_number = match.group(1)
                current_section = {
                    'number': section_number,
                    'title': line.strip(),
                    'content': ''
                }
            elif current_section:
                current_section['content'] += line + '\n'
        
        # Den letzten Abschnitt hinzufügen
        if current_section:
            sections.append(current_section)
        
        return sections
    
    def extract_fine_catalog(self, text: str) -> List[Dict]:
        """
        Spezielle Funktion zur Extraktion von Bußgeldkatalog-Einträgen aus Text.
        
        Args:
            text: Der zu verarbeitende Text
            
        Returns:
            Eine Liste von Dictionaries mit den extrahierten Bußgeldkatalog-Einträgen
        """
        # Fallback für leeren oder ungültigen Text
        if not text or not isinstance(text, str):
            return [{'category': 'Allgemein', 'violation': 'Fehlerhafte Eingabe', 'description': 'Fehlerhafte Eingabe', 'amount': 0, 'prison_days': 0, 'notes': 'Automatisch generiert nach Fehler'}]
            
        try:
            # Versuche zuerst die strukturierte HTML-Tabellenextraktion
            if "<table" in text and "</table>" in text:
                catalog_entries = self.extract_fine_catalog_from_html(text)
                if catalog_entries and len(catalog_entries) > 0:
                    return catalog_entries
        except Exception as e:
            print(f"Fehler bei HTML-Extraktion: {str(e)}", file=sys.stderr)
            # Fortfahren mit regulärer Textextraktion
        
        # Einfache Implementierung: Suche nach Mustern wie "Verstoß: XYZ" oder "Bußgeld: 123"
        try:
            violations = re.findall(r'(?:Verstoß|Delikt|Tat):\s*([^\n]+)', text)
            amounts = re.findall(r'(?:Bußgeld|Strafe|Geldstrafe):\s*(\d+(?:[.,]\d+)?)', text)
            amount_ranges = re.findall(r'(?:Bußgeld|Strafe|Geldstrafe)?\s*:?\s*(\d+(?:[.,]\d+)?)\s*(?:-|–|bis|to)\s*(\d+(?:[.,]\d+)?)', text, flags=re.IGNORECASE)
            prison_days = re.findall(r'(?:Haftzeit|Gefängnis|Freiheitsstrafe):\s*(\d+)', text)
            categories = re.findall(r'(?:Kategorie|Bereich):\s*([^\n]+)', text)
            notes = re.findall(r'(?:Notizen|Anmerkungen|Hinweise):\s*([^\n]+)', text)
            community_services = re.findall(r'(?:Strafarbeit|Community Service|Dienststunden|Arbeitsstunden):\s*(\d+(?:[.,]\d+)?)', text, flags=re.IGNORECASE)
        except Exception as e:
            print(f"Fehler bei Regex-Extraktion: {str(e)}", file=sys.stderr)
            return [{'category': 'Allgemein', 'violation': 'Extraktionsfehler', 'description': 'Fehler bei der Textextraktion', 'amount': 0, 'amount_min': 0, 'amount_max': 0, 'community_service_hours': 0, 'prison_days': 0, 'notes': str(e)}]
        
        # Bestimme die maximale Anzahl von Einträgen
        max_entries = max(
            len(violations),
            len(amounts),
            len(amount_ranges),
            len(prison_days),
            len(categories)
        )
        
        if max_entries == 0:
            # Versuche allgemeinere Muster, die in Zeilen vorkommen könnten
            lines = text.split('\n')
            fallback_entries = []
            for line in lines:
                # Suche nach Zeilen, die ein Paragraphenzeichen und Geldbeträge enthalten
                if ('§' in line or 'Paragraph' in line) and (re.search(r'\$\d+', line) or re.search(r'\d+\s*Euro', line)):
                    match_offense = re.search(r'§\s*\d+\s*(.+?)(?:\$|\d+\s*Euro|$)', line)
                    match_amount = re.search(r'[\$€](\d+(?:[.,]\d+)?)', line)
                    match_prison = re.search(r'(\d+)\s*(?:Tage|Tagen|Tag)', line)
                    match_range = re.search(r'(\d+(?:[.,]\d+)?)\s*(?:-|–|bis)\s*(\d+(?:[.,]\d+)?)', line)
                    match_cs = re.search(r'(\d+(?:[.,]\d+)?)\s*(?:Stunden|Std|h)', line, flags=re.IGNORECASE)
                    
                    entry = {
                        'category': 'Allgemein',
                        'violation': match_offense.group(1).strip() if match_offense else line,
                        'amount': float(match_amount.group(1).replace(',', '.')) if match_amount else float(match_range.group(2).replace(',', '.')) if match_range else 0,
                        'amount_min': float(match_range.group(1).replace(',', '.')) if match_range else float(match_amount.group(1).replace(',', '.')) if match_amount else 0,
                        'amount_max': float(match_range.group(2).replace(',', '.')) if match_range else float(match_amount.group(1).replace(',', '.')) if match_amount else 0,
                        'prison_days': int(match_prison.group(1)) if match_prison else 0,
                        'community_service_hours': int(float(match_cs.group(1).replace(',', '.'))) if match_cs else 0,
                        'description': line.strip(),
                        'notes': ''
                    }
                    fallback_entries.append(entry)
            
            return fallback_entries
        
        # Erzeuge die Einträge
        catalog = []
        for i in range(max_entries):
            entry = {}
            
            if i < len(categories):
                entry['category'] = categories[i].strip()
            else:
                entry['category'] = 'Allgemein'
            
            if i < len(violations):
                entry['violation'] = violations[i].strip()
            else:
                entry['violation'] = f'Unbekannter Verstoß {i+1}'
            
            amount_value = amount_min = amount_max = 0.0
            if i < len(amount_ranges):
                try:
                    amount_min = float(amount_ranges[i][0].replace(',', '.'))
                    amount_max = float(amount_ranges[i][1].replace(',', '.'))
                    amount_value = amount_max or amount_min
                except (ValueError, IndexError):
                    pass
            elif i < len(amounts):
                try:
                    amount_value = amount_min = amount_max = float(amounts[i].replace(',', '.'))
                except ValueError:
                    pass
            entry['amount'] = amount_value
            entry['amount_min'] = amount_min
            entry['amount_max'] = amount_max
            
            if i < len(prison_days):
                try:
                    entry['prison_days'] = int(prison_days[i])
                except ValueError:
                    entry['prison_days'] = 0
            else:
                entry['prison_days'] = 0

            if i < len(community_services):
                try:
                    entry['community_service_hours'] = int(float(community_services[i].replace(',', '.')))
                except ValueError:
                    entry['community_service_hours'] = 0
            else:
                entry['community_service_hours'] = 0
            
            entry['description'] = entry['violation']
            
            if i < len(notes):
                entry['notes'] = notes[i].strip()
            else:
                entry['notes'] = ''
            
            catalog.append(entry)
        
        return catalog
        
    def extract_fine_catalog_from_html(self, html_content: str) -> List[Dict]:
        """
        Extrahiert Bußgeldkatalog-Einträge aus einer HTML-Tabelle.
        
        Args:
            html_content: HTML-Inhalt mit einer Tabelle
            
        Returns:
            Eine Liste von Dictionaries mit den extrahierten Bußgeldkatalog-Einträgen
        """
        soup = BeautifulSoup(html_content, 'html.parser')
        catalog = []
        
        # Suche nach Tabellen
        tables = soup.find_all('table')
        
        for table in tables:
            current_category = 'Allgemein'
            
            rows = table.find_all('tr')
            if not rows:
                continue

            header_cells = rows[0].find_all(['th', 'td'])
            header_titles = [cell.get_text(strip=True).lower() for cell in header_cells]
            header_map = {}
            for idx, title in enumerate(header_titles):
                if 'kategorie' in title:
                    header_map['category'] = idx
                if 'verstoß' in title or 'tat' in title:
                    header_map['violation'] = idx
                if 'bußgeld' in title or 'strafe' in title:
                    header_map['amount'] = idx
                if 'haft' in title or 'tage' in title or 'freiheitsstrafe' in title:
                    header_map['prison'] = idx
                if 'strafarbeit' in title or 'community' in title or 'arbeits' in title:
                    header_map['community_service'] = idx
                if 'anmerk' in title or 'notiz' in title or 'hinweis' in title:
                    header_map['notes'] = idx

            start_index = 1 if header_cells and any(cell.name == 'th' for cell in header_cells) else 0

            for row_index, row in enumerate(rows[start_index:], start=start_index):
                cells = row.find_all('td')
                if not cells:
                    continue

                cell_texts = [cell.get_text(strip=True) for cell in cells]
                if not any(cell_texts):
                    continue

                if len(cells) >= 2:
                    potential_category = cell_texts[1]
                    if potential_category.startswith(('I.', 'II.', 'III.', 'IV.', 'V.')):
                        current_category = potential_category
                        continue

                category_idx = header_map.get('category')
                category_value = current_category
                if category_idx is not None and category_idx < len(cell_texts) and cell_texts[category_idx]:
                    category_value = cell_texts[category_idx]

                violation_idx = header_map.get('violation', 1 if len(cell_texts) > 1 else 0)
                violation = cell_texts[violation_idx] if violation_idx < len(cell_texts) else ''
                if not violation:
                    continue

                amount_idx = header_map.get('amount', 2 if len(cell_texts) > 2 else 0)
                amount_text = cell_texts[amount_idx] if amount_idx < len(cell_texts) else ''
                amount_value, amount_min, amount_max = self._parse_amount_field(amount_text)

                prison_days = 0
                prison_idx = header_map.get('prison', 3 if len(cell_texts) > 3 else None)
                if prison_idx is not None and prison_idx < len(cell_texts):
                    prison_match = re.search(r'(\d+)', cell_texts[prison_idx])
                    if prison_match:
                        try:
                            prison_days = int(prison_match.group(1))
                        except ValueError:
                            prison_days = 0

                community_service_hours = 0
                community_idx = header_map.get('community_service')
                if community_idx is not None and community_idx < len(cell_texts):
                    community_service_hours = self._parse_community_service(cell_texts[community_idx])
                elif len(cell_texts) >= 6:
                    community_service_hours = self._parse_community_service(cell_texts[4])

                notes_idx = header_map.get('notes', len(cell_texts) - 1)
                notes = cell_texts[notes_idx] if notes_idx is not None and notes_idx < len(cell_texts) else ''

                entry = {
                    'category': category_value,
                    'violation': violation,
                    'description': violation,
                    'amount': amount_value,
                    'amount_min': amount_min,
                    'amount_max': amount_max,
                    'prison_days': prison_days,
                    'community_service_hours': community_service_hours,
                    'notes': notes
                }

                catalog.append(entry)
        
        return catalog

def main():
    """Hauptfunktion zum Ausführen des Parsers über die Kommandozeile."""
    parser = argparse.ArgumentParser(description='Dokument-Parser für das Justizsystem')
    parser.add_argument('input', help='Datei oder URL zum Parsen')
    parser.add_argument('--output', help='Ausgabedatei (JSON)')
    parser.add_argument('--mode', choices=['text', 'sections', 'catalog'], 
                        default='text', help='Ausgabemodus (Text, Abschnitte oder Bußgeldkatalog)')
    
    args = parser.parse_args()
    
    doc_parser = DocumentParser()
    result = doc_parser.detect_and_parse(args.input)
    
    if not result['success']:
        print(f"Fehler: {result.get('error', 'Unbekannter Fehler')}", file=sys.stderr)
        sys.exit(1)
    
    # Verarbeite den Text je nach Modus
    if args.mode == 'sections':
        sections = doc_parser.extract_sections(result['content'])
        output = json.dumps(sections, ensure_ascii=False, indent=2)
    elif args.mode == 'catalog':
        catalog = doc_parser.extract_fine_catalog(result['content'])
        output = json.dumps(catalog, ensure_ascii=False, indent=2)
    else:
        # Text-Modus
        output = json.dumps(result, ensure_ascii=False, indent=2)
    
    # Ausgabe
    if args.output:
        with open(args.output, 'w', encoding='utf-8') as f:
            f.write(output)
    else:
        print(output)

if __name__ == '__main__':
    main()