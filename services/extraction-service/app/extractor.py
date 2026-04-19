from __future__ import annotations

import os
import re
from dataclasses import dataclass
from typing import Any

import cv2
import numpy as np
from rapidocr_onnxruntime import RapidOCR


PHONE_PATTERN = re.compile(r"(?:\+?60|0)\s*1\d(?:[\s-]*\d){7,9}")
DATE_PATTERN = re.compile(r"\b\d{2}/\d{2}/\d{4}\b")
LABEL_BLACKLIST = {
    "raw",
    "whatsapp",
    "name",
    "phone number",
    "work phone",
    "get leads",
}


@dataclass(slots=True)
class OCRLine:
    text: str
    confidence: float
    bbox: list[list[float]]
    center_x: float
    center_y: float
    height: float


_rapid_ocr_engine: RapidOCR | None = None


def _get_rapid_ocr() -> RapidOCR:
    global _rapid_ocr_engine

    if _rapid_ocr_engine is None:
        _rapid_ocr_engine = RapidOCR()

    return _rapid_ocr_engine


def _decode_image(file_bytes: bytes) -> np.ndarray:
    image_array = np.frombuffer(file_bytes, dtype=np.uint8)
    image = cv2.imdecode(image_array, cv2.IMREAD_COLOR)

    if image is None:
        raise ValueError("Unable to decode uploaded image bytes.")

    return image


def _preprocess_variants(image: np.ndarray) -> list[np.ndarray]:
    variants = [image]
    upscale = cv2.resize(image, None, fx=1.6, fy=1.6, interpolation=cv2.INTER_CUBIC)
    gray = cv2.cvtColor(upscale, cv2.COLOR_BGR2GRAY)
    denoised = cv2.bilateralFilter(gray, 7, 50, 50)
    thresholded = cv2.adaptiveThreshold(
        denoised,
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY,
        31,
        11,
    )
    variants.extend([upscale, cv2.cvtColor(thresholded, cv2.COLOR_GRAY2BGR)])
    return variants


def _score_ocr_lines(lines: list[OCRLine]) -> float:
    if not lines:
        return 0.0

    average_confidence = sum(line.confidence for line in lines) / len(lines)
    phone_hits = sum(1 for line in lines if PHONE_PATTERN.search(line.text))
    meaningful_lines = sum(1 for line in lines if len(line.text.strip()) >= 4)

    return (
        len(lines)
        + (average_confidence * 10.0)
        + (phone_hits * 12.0)
        + (meaningful_lines * 0.35)
    )


def _is_good_enough_ocr(lines: list[OCRLine]) -> bool:
    if not lines:
        return False

    average_confidence = sum(line.confidence for line in lines) / len(lines)
    phone_hits = sum(1 for line in lines if PHONE_PATTERN.search(line.text))
    meaningful_lines = sum(1 for line in lines if len(line.text.strip()) >= 4)

    if phone_hits >= 1 and meaningful_lines >= 3 and average_confidence >= 0.58:
        return True

    if len(lines) >= 8 and meaningful_lines >= 6 and average_confidence >= 0.68:
        return True

    return False


def _run_ocr(image: np.ndarray) -> tuple[list[OCRLine], str]:
    engine = _get_rapid_ocr()
    best_lines: list[OCRLine] = []
    best_score = float('-inf')

    for index, variant in enumerate(_preprocess_variants(image)):
        results, _ = engine(variant)
        lines = _normalize_ocr_results(results or [])
        score = _score_ocr_lines(lines)

        if score > best_score:
            best_lines = lines
            best_score = score

        # Fast path: stop once earlier passes already look reliable enough.
        if _is_good_enough_ocr(lines):
            best_lines = lines
            break

        # After the second pass, only continue to the thresholded fallback if signal is still weak.
        if index == 1 and best_score >= 14.0:
            break

    raw_text = "\n".join(line.text for line in best_lines)

    return best_lines, raw_text


def _normalize_ocr_results(results: list[list[Any]]) -> list[OCRLine]:
    lines: list[OCRLine] = []

    for item in results:
        if len(item) < 3:
            continue

        bbox = [[float(point[0]), float(point[1])] for point in item[0]]
        text = str(item[1]).strip()
        confidence = float(item[2])

        if not text:
            continue

        xs = [point[0] for point in bbox]
        ys = [point[1] for point in bbox]
        lines.append(
            OCRLine(
                text=text,
                confidence=confidence,
                bbox=bbox,
                center_x=(min(xs) + max(xs)) / 2,
                center_y=(min(ys) + max(ys)) / 2,
                height=max(ys) - min(ys),
            )
        )

    lines.sort(key=lambda line: (line.center_y, line.center_x))
    return lines


def _cluster_lines(lines: list[OCRLine]) -> list[list[OCRLine]]:
    if not lines:
        return []

    median_height = sorted(line.height for line in lines)[len(lines) // 2] if lines else 18.0
    gap_threshold = max(28.0, median_height * 2.2)
    blocks: list[list[OCRLine]] = [[lines[0]]]

    for line in lines[1:]:
        previous = blocks[-1][-1]
        if line.center_y - previous.center_y > gap_threshold:
            blocks.append([line])
        else:
            blocks[-1].append(line)

    return blocks


def _normalize_phone_number(text: str) -> str | None:
    digits = re.sub(r"[^\d+]", "", text)
    digits = digits.replace("++", "+")

    if digits.startswith("+60"):
        body = re.sub(r"\D", "", digits[1:])
        return f"+{body}" if len(body) >= 10 else None

    pure_digits = re.sub(r"\D", "", digits)

    if pure_digits.startswith("60"):
        return f"+{pure_digits}" if len(pure_digits) >= 10 else None

    if pure_digits.startswith("0") and len(pure_digits) >= 10:
        return f"+6{pure_digits}"

    return None


def _is_noise_text(text: str) -> bool:
    normalized = re.sub(r"\s+", " ", text.strip().lower())

    if not normalized:
        return True

    if normalized in LABEL_BLACKLIST:
        return True

    if DATE_PATTERN.search(normalized):
        return True

    if normalized.startswith("raw") and len(normalized.split()) <= 2:
        return True

    return False


def _score_name_candidate(line: OCRLine, phone_line: OCRLine) -> float:
    text = line.text.strip()
    word_count = len(text.split())
    compact_text = re.sub(r"\s+", "", text)
    score = 0.0

    if line.center_y <= phone_line.center_y:
        score += 3.0

    score += max(0.0, 1.5 - (abs(phone_line.center_y - line.center_y) / 90.0))

    if line.center_x < phone_line.center_x:
        score += 1.25

    if word_count >= 2:
        score += 2.0
    elif len(text) >= 6:
        score += 0.8

    if len(compact_text) == 1:
        score -= 4.5

    digit_count = sum(character.isdigit() for character in compact_text)
    alpha_count = sum(character.isalpha() for character in compact_text)

    if digit_count:
        if alpha_count and len(compact_text) >= 5:
            score -= 0.35
        else:
            score -= 2.0

    if len(compact_text) >= 8:
        score += 0.6

    if text.isupper() and word_count == 1:
        score -= 0.3

    return score


def _candidate_name_from_block(block: list[OCRLine], phone_line: OCRLine) -> str | None:
    name_candidates = [
        line for line in block
        if not _is_noise_text(line.text)
        and not PHONE_PATTERN.search(line.text)
    ]

    best_name = None
    best_score = float("-inf")
    for candidate in name_candidates:
        score = _score_name_candidate(candidate, phone_line)
        if score > best_score:
            best_score = score
            best_name = candidate.text.strip()

    return best_name


def _extract_records(blocks: list[list[OCRLine]]) -> list[dict[str, Any]]:
    records: list[dict[str, Any]] = []

    for index, block in enumerate(blocks):
        phone_lines: list[tuple[OCRLine, str]] = []

        for line in block:
            match = PHONE_PATTERN.search(line.text)
            if not match:
                continue

            normalized_phone = _normalize_phone_number(match.group())
            if not normalized_phone:
                continue

            phone_lines.append((line, normalized_phone))

        if not phone_lines:
            continue

        unique_phones: dict[str, OCRLine] = {}
        for phone_line, normalized_phone in phone_lines:
            unique_phones.setdefault(normalized_phone, phone_line)

        primary_phone, primary_phone_line = next(iter(unique_phones.items()))

        best_name = _candidate_name_from_block(block, primary_phone_line)

        if not best_name and index > 0:
            previous_block = blocks[index - 1]
            best_name = _candidate_name_from_block(previous_block, primary_phone_line)

        block_confidence = min(
            0.99,
            max(
                0.55,
                sum(line.confidence for line in block) / len(block) if block else 0.55,
            ),
        )

        records.append({
            'name': best_name,
            'phone_number': primary_phone,
            'normalized_phone_number': primary_phone,
            'confidence': round(block_confidence, 4),
            'raw_text': ' | '.join(line.text for line in block),
            'metadata': {
                'engine': 'rapidocr',
                'block_line_count': len(block),
                'additional_phones': list(unique_phones.keys())[1:],
            },
        })

    return records


def extract_leads(*, file_bytes: bytes, filename: str, batch_id: str | None, extraction_image_id: str | None) -> dict[str, Any]:
    image = _decode_image(file_bytes)
    lines, raw_text = _run_ocr(image)
    blocks = _cluster_lines(lines)
    records = _extract_records(blocks)

    return {
        "engine": os.getenv("EXTRACTION_OCR_ENGINE", "rapidocr"),
        "raw_text": raw_text,
        "records": records,
        "meta": {
            "batch_id": batch_id,
            "extraction_image_id": extraction_image_id,
            "filename": filename,
            "line_count": len(lines),
            "block_count": len(blocks),
        },
    }