#!/usr/bin/env python3
"""Build .po/.mo translation files for picot-aio-ai-content-optimizer."""

from __future__ import annotations

import json
import re
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

PLUGIN_DIR = Path(__file__).resolve().parent.parent
LANG_DIR = PLUGIN_DIR / "languages"
DOMAIN = "picot-aio-ai-content-optimizer"

LOCALES = {
    "ja": ("ja", "Japanese", "nplurals=1; plural=0;"),
    "de_DE": ("de_DE", "German", "nplurals=2; plural=(n != 1);"),
    "fr_FR": ("fr_FR", "French", "nplurals=2; plural=(n > 1);"),
    "es_ES": ("es_ES", "Spanish", "nplurals=2; plural=(n != 1);"),
    "zh_TW": ("zh_TW", "Chinese (Taiwan)", "nplurals=1; plural=0;"),
    "it_IT": ("it_IT", "Italian", "nplurals=2; plural=(n != 1);"),
    "pt_BR": ("pt_BR", "Portuguese (Brazil)", "nplurals=2; plural=(n > 1);"),
    "nl_NL": ("nl_NL", "Dutch", "nplurals=2; plural=(n != 1);"),
    "ko_KR": ("ko_KR", "Korean", "nplurals=1; plural=0;"),
    "pl_PL": ("pl_PL", "Polish", "nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);"),
    "ru_RU": ("ru_RU", "Russian", "nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);"),
    "id_ID": ("id_ID", "Indonesian", "nplurals=1; plural=0;"),
    "vi": ("vi", "Vietnamese", "nplurals=1; plural=0;"),
    "tr_TR": ("tr_TR", "Turkish", "nplurals=2; plural=(n > 1);"),
    "th": ("th", "Thai", "nplurals=1; plural=0;"),
}

LEGACY_LOCALES = ("ja", "de_DE", "fr_FR", "es_ES", "zh_TW")
BASE_LOCALE_FOR_NEW = "de_DE"

# Map new msgid -> legacy msgid (git history).
MSGID_ALIASES = {
    "Rewrite completed successfully!": "Rewrite Success!",
    "SEO/AIO Analyze": "Analyze Content",
    "AI Rewrite": "Gemini Rewrite",
    "Suggest Images": "Discover Image Opportunities",
    "Discovering images...": "Discovering image opportunities...",
    "Generate and Place": "🚀 Generate and Place",
    "Batch Generate and Place": "🚀 Batch Generate and Place",
    "Saved: ": "💾 Saved: ",
    "Done!": "✅ Done!",
    "Done": "✅ Done",
    "Error": "❌ Error",
    "Failed to insert image.": "Failed to insert image. It has been saved to the media library.",
    "No suitable image placement found": "No placement opportunities found (excluding areas near existing images)",
    "Image Generation": "Image Generation Function",
    "Image Generation Model": "Image Prompt Model",
    "Fetch Latest Model List": "Fetch Latest Model List",
    "After saving the API key, click the button above to add the latest available models to the list.": (
        "After saving the API key, you can add the latest available models to the "
        "dropdown using the button above."
    ),
    "Select the style for generated images. It will be automatically added to the prompt.": (
        "Select the style for generated images. This will be automatically added to "
        "prompts."
    ),
    "Showing the last 20 analysis records. Click a post ID to open the editor, or use Expand to view results in a centered modal.": (
        "Showing the last 20 analysis records. Click a row to view details."
    ),
    "Save Settings": "Save Settings",
    "Picot AIO AI Content Optimizer": "Picot AIO AI Content Optimizer",
}

STRING_PATTERN = re.compile(
    r"(?:__|esc_html__|esc_attr__|esc_html_e)\(\s*'((?:\\'|[^'])*)'",
)


def unescape_po(parts: list[str]) -> str:
    return "".join(parts).replace("\\n", "\n").replace('\\"', '"').replace("\\\\", "\\")


def parse_po_content(content: str) -> dict[str, str]:
    entries: dict[str, str] = {}
    current_id: list[str] = []
    current_str: list[str] = []
    mode: str | None = None
    for line in content.splitlines() + [""]:
        if line.startswith("msgid "):
            if current_id and "".join(current_id):
                entries[unescape_po(current_id)] = unescape_po(current_str) if current_str else ""
            current_id = [re.match(r'msgid "(.*)"', line).group(1)]
            current_str = []
            mode = "id"
        elif line.startswith("msgstr "):
            current_str = [re.match(r'msgstr "(.*)"', line).group(1)]
            mode = "str"
        elif line.startswith('"'):
            value = line.strip()[1:-1]
            if mode == "id":
                current_id.append(value)
            elif mode == "str":
                current_str.append(value)
        elif line.strip() == "":
            if current_id and "".join(current_id):
                entries[unescape_po(current_id)] = unescape_po(current_str) if current_str else ""
            current_id, current_str, mode = [], [], None
    return entries


def load_legacy_po(locale: str) -> dict[str, str]:
    path = f"languages/{DOMAIN}-{locale}.po"
    try:
        content = subprocess.check_output(
            ["git", "show", f"HEAD:{path}"],
            cwd=PLUGIN_DIR,
            text=True,
        )
    except subprocess.CalledProcessError:
        return {}
    return parse_po_content(content)


def collect_msgids() -> list[str]:
    msgids: set[str] = set()
    for php in PLUGIN_DIR.rglob("*.php"):
        text = php.read_text(encoding="utf-8")
        for match in STRING_PATTERN.finditer(text):
            if "picot-aio-ai-content-optimizer" not in text[match.start() : match.start() + 250]:
                continue
            msgids.add(match.group(1).replace("\\'", "'"))
    return sorted(msgids)


def load_manual_translations() -> dict[str, dict[str, str]]:
    path = LANG_DIR / "extra-translations.json"
    if not path.exists():
        return {}
    return json.loads(path.read_text(encoding="utf-8"))


def resolve_translation(msgid: str, legacy: dict[str, str], manual: dict[str, str]) -> str:
    if msgid in manual and manual[msgid]:
        return manual[msgid]
    if msgid in legacy and legacy[msgid]:
        return legacy[msgid]
    alias = MSGID_ALIASES.get(msgid)
    if alias and alias in legacy and legacy[alias]:
        return legacy[alias]
    return ""


def build_locale_entries(
    msgids: list[str],
    legacy: dict[str, str],
    manual: dict[str, str],
    fallback: dict[str, str],
) -> dict[str, str]:
    entries: dict[str, str] = {}
    for msgid in msgids:
        value = resolve_translation(msgid, legacy, manual)
        if not value and fallback:
            value = resolve_translation(msgid, fallback, manual)
        if not value:
            value = msgid
        entries[msgid] = value
    return entries


def escape_po(value: str) -> str:
    return value.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n")


def write_po(locale: str, lang_name: str, plural_forms: str, entries: dict[str, str], msgids: list[str]) -> None:
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M+0000")
    lines = [
        'msgid ""',
        'msgstr ""',
        f'"Project-Id-Version: Picot AIO AI Content Optimizer 1.0.0\\n"',
        f'"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/{DOMAIN}/\\n"',
        f'"POT-Creation-Date: {now}\\n"',
        f'"PO-Revision-Date: {now}\\n"',
        f'"Last-Translator: Picot\\n"',
        f'"Language-Team: {lang_name}\\n"',
        f'"Language: {locale}\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        f'"Plural-Forms: {plural_forms}\\n"',
        f'"X-Domain: {DOMAIN}\\n"',
        "",
    ]
    for msgid in msgids:
        msgstr = entries[msgid]
        if "\n" in msgid:
            lines.append('msgid ""')
            for part in msgid.split("\n"):
                lines.append(f'"{escape_po(part)}\\n"')
        else:
            lines.append(f'msgid "{escape_po(msgid)}"')
        if "\n" in msgstr:
            lines.append('msgstr ""')
            for part in msgstr.split("\n"):
                lines.append(f'"{escape_po(part)}\\n"')
        else:
            lines.append(f'msgstr "{escape_po(msgstr)}"')
        lines.append("")
    out = LANG_DIR / f"{DOMAIN}-{locale}.po"
    out.write_text("\n".join(lines), encoding="utf-8")
    mo = LANG_DIR / f"{DOMAIN}-{locale}.mo"
    subprocess.run(["msgfmt", "-o", str(mo), str(out)], check=True)


def main() -> int:
    msgids = collect_msgids()
    manual_all = load_manual_translations()
    legacy_maps = {loc: load_legacy_po(loc) for loc in LEGACY_LOCALES}
    base_legacy = legacy_maps[BASE_LOCALE_FOR_NEW]

    for locale, (_, lang_name, plural) in LOCALES.items():
        legacy = legacy_maps.get(locale, {})
        manual = manual_all.get(locale, {})
        fallback = base_legacy if locale not in LEGACY_LOCALES else {}
        entries = build_locale_entries(msgids, legacy, manual, fallback)
        write_po(locale, lang_name, plural, entries, msgids)
        translated = sum(1 for m in msgids if entries[m] != m)
        print(f"{locale}: {translated}/{len(msgids)} translated")

    pot_entries = {m: "" for m in msgids}
    write_po("en_US", "English", "nplurals=2; plural=(n != 1);", pot_entries, msgids)
    pot_path = LANG_DIR / f"{DOMAIN}.pot"
    pot_path.write_text((LANG_DIR / f"{DOMAIN}-en_US.po").read_text(encoding="utf-8"), encoding="utf-8")
    (LANG_DIR / f"{DOMAIN}-en_US.po").unlink(missing_ok=True)
    (LANG_DIR / f"{DOMAIN}-en_US.mo").unlink(missing_ok=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
