# Extraction Service

This service owns OCR orchestration and lead candidate extraction for uploaded screenshots.

## Scope

- Receive one image per request
- Run OCR via pluggable engine adapters
- Return structured lead candidates with confidence
- Stay independent from Laravel so it can scale separately

## Initial Contract

`POST /v1/extractions/images`

Multipart form fields:

- `file`: screenshot image
- `batch_id`: parent batch id from Laravel
- `extraction_image_id`: image record id from Laravel
- `original_name`: original file name

Response body:

```json
{
  "engine": "mock-pipeline",
  "raw_text": "...",
  "records": [
    {
      "name": "Jane Doe",
      "phone_number": "+60 12-345 6789",
      "normalized_phone_number": "+60123456789",
      "confidence": 0.91,
      "raw_text": "Jane Doe +60 12-345 6789",
      "metadata": {
        "source": "ocr"
      }
    }
  ],
  "meta": {
    "batch_id": 12,
    "extraction_image_id": 44
  }
}
```

## Local Run

```bash
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```

## Next Work

- add PaddleOCR adapter
- add phone normalization
- add provider fallback adapter for Google Vision
- add screenshot block segmentation rules