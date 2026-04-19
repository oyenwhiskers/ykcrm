from __future__ import annotations

from fastapi import FastAPI, File, Form, UploadFile

from app.extractor import extract_leads

app = FastAPI(title="YKCRM Extraction Service", version="0.1.0")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/v1/extractions/images")
async def extract_image(
    file: UploadFile = File(...),
    batch_id: str | None = Form(default=None),
    extraction_image_id: str | None = Form(default=None),
    original_name: str | None = Form(default=None),
) -> dict:
    file_bytes = await file.read()

    return extract_leads(
        file_bytes=file_bytes,
        filename=original_name or file.filename,
        batch_id=batch_id,
        extraction_image_id=extraction_image_id,
    )