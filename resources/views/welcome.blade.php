<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Lead Extraction</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        <style>
            :root {
                --page-bg: #f5f8ff;
                --surface: #ffffff;
                --surface-soft: #f7faff;
                --surface-muted: #f3f7ff;
                --border: #dfe7f5;
                --border-strong: #cad8ee;
                --text: #0f1728;
                --muted: #7d8aa5;
                --primary: #1f5b96;
                --primary-dark: #184a79;
                --shadow: 0 10px 26px rgba(20, 43, 86, 0.06);
                --radius-lg: 18px;
                --radius-md: 12px;
                --radius-sm: 10px;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: 'Instrument Sans', sans-serif;
                color: var(--text);
                background: linear-gradient(180deg, #f8fbff 0%, var(--page-bg) 100%);
            }

            .page {
                width: min(1600px, calc(100vw - 8px));
                margin: 4px auto;
                padding: clamp(14px, 1.4vw, 22px) clamp(16px, 1.9vw, 28px) clamp(18px, 2.1vw, 26px);
                border: 1px dashed var(--border-strong);
                border-radius: 10px;
                background: rgba(248, 251, 255, 0.92);
            }

            .topbar {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 16px;
            }

            .eyebrow {
                margin: 0 0 4px;
                font-size: 10px;
                font-weight: 700;
                letter-spacing: 0.16em;
                text-transform: uppercase;
                color: #7e8ba7;
            }

            .title {
                margin: 0;
                font-size: clamp(34px, 2.7vw, 44px);
                font-weight: 700;
                letter-spacing: -0.04em;
                line-height: 1.02;
            }

            .tabs {
                display: inline-flex;
                padding: 4px;
                border: 1px solid var(--border);
                border-radius: 10px;
                background: #edf3ff;
            }

            .tab {
                border: 0;
                background: transparent;
                color: #5d6f91;
                font: inherit;
                font-size: 13px;
                font-weight: 600;
                padding: 10px 18px;
                border-radius: 8px;
            }

            .tab.active {
                background: #ffffff;
                color: #25486c;
                box-shadow: 0 1px 2px rgba(18, 34, 68, 0.08);
            }

            .panel {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: var(--radius-sm);
                box-shadow: var(--shadow);
            }

            .upload-panel {
                padding: clamp(16px, 1.7vw, 26px);
                margin-bottom: 18px;
                background: linear-gradient(180deg, #f8fbff 0%, #f5f8ff 100%);
            }

            .dropzone {
                position: relative;
                min-height: clamp(220px, 24vw, 310px);
                display: flex;
                align-items: center;
                justify-content: center;
                text-align: center;
                border: 1px solid #e8eef9;
                border-radius: 6px;
                background: #f2f6ff;
                transition: border-color 160ms ease, background 160ms ease, transform 160ms ease;
            }

            .dropzone.dragover {
                border-color: #9fb9df;
                background: #edf4ff;
                transform: translateY(-1px);
            }

            .dropzone-inner {
                width: min(720px, 100%);
                padding: clamp(16px, 1.7vw, 22px);
            }

            .icon-box {
                width: 50px;
                height: 50px;
                margin: 0 auto 16px;
                display: grid;
                place-items: center;
                border-radius: 12px;
                background: #e7f0ff;
                color: #2b5b8f;
            }

            .upload-title {
                margin: 0;
                font-size: clamp(30px, 2.4vw, 38px);
                font-weight: 700;
                letter-spacing: -0.03em;
                line-height: 1.08;
            }

            .upload-copy {
                margin: 10px auto 22px;
                max-width: 560px;
                font-size: clamp(13px, 1vw, 15px);
                line-height: 1.55;
                color: var(--muted);
            }

            .button-primary,
            .table-action {
                appearance: none;
                border: 0;
                cursor: pointer;
                font: inherit;
            }

            .button-primary {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 136px;
                min-height: 42px;
                padding: 0 20px;
                border-radius: 6px;
                background: linear-gradient(180deg, #26629a 0%, #1d527f 100%);
                color: #ffffff;
                font-size: 13px;
                font-weight: 700;
                box-shadow: 0 3px 10px rgba(31, 91, 150, 0.2);
            }

            .file-input {
                position: absolute;
                inset: 0;
                opacity: 0;
                pointer-events: none;
            }

            .selected-files {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
                gap: 10px;
                width: min(560px, 100%);
                margin: 18px auto 0;
                padding: 0;
                list-style: none;
            }

            .selected-file {
                display: grid;
                grid-template-columns: auto minmax(0, 1fr) auto;
                align-items: center;
                gap: 12px;
                width: 100%;
                padding: 12px 14px;
                border: 1px solid #d7e3f6;
                border-radius: 16px;
                background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
                box-shadow: 0 6px 18px rgba(29, 58, 108, 0.06);
                color: #46617e;
                font-size: 12px;
                font-weight: 600;
                text-align: left;
            }

            .selected-file-icon {
                width: 34px;
                height: 34px;
                display: grid;
                place-items: center;
                border-radius: 12px;
                background: #edf4ff;
                color: #315f95;
                flex: 0 0 auto;
            }

            .selected-file-content {
                min-width: 0;
            }

            .selected-file-name,
            .selected-file-meta {
                min-width: 0;
            }

            .selected-file-name {
                display: block;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                color: #2a4770;
                font-size: 13px;
                font-weight: 700;
            }

            .selected-file-meta {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-top: 4px;
                color: #7c8ba5;
                font-size: 11px;
                font-weight: 600;
                flex-wrap: wrap;
            }

            .selected-file-size {
                display: inline-flex;
                align-items: center;
                padding: 3px 8px;
                border-radius: 999px;
                background: #eef4ff;
                color: #46617e;
                font-size: 10px;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }

            .selected-file-remove {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex: 0 0 auto;
                width: 26px;
                height: 26px;
                border: 0;
                border-radius: 999px;
                background: #edf4ff;
                color: #567291;
                cursor: pointer;
                font: inherit;
                font-size: 15px;
                font-weight: 700;
                line-height: 1;
                transition: background 160ms ease, color 160ms ease, transform 160ms ease;
            }

            .selected-file-remove:hover:not(:disabled) {
                background: #e1ecff;
                color: #2f5888;
                transform: translateY(-1px);
            }

            .selected-file-remove:disabled {
                cursor: not-allowed;
                opacity: 0.5;
            }

            .status-chip {
                display: inline-flex;
                align-items: center;
                padding: 6px 10px;
                border-radius: 999px;
                background: #e6f0ff;
                color: #255486;
                font-size: 11px;
                font-weight: 700;
            }

            .status-chip.processing,
            .status-chip.queued,
            .status-chip.uploading,
            .status-chip.saving {
                background: #e6f0ff;
                color: #255486;
            }

            .status-chip.completed,
            .status-chip.saved,
            .status-chip.completed_with_failures {
                background: #e8f6ee;
                color: #2a764f;
            }

            .status-chip.failed,
            .status-chip.error {
                background: #fff1ef;
                color: #b6534a;
            }

            .status-chip.idle {
                background: #eef4ff;
                color: #31598b;
            }

            .progress-panel {
                display: none;
                margin-bottom: 16px;
                padding: 16px;
            }

            .progress-panel.active {
                display: block;
            }

            .progress-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 14px;
            }

            .progress-intro {
                flex: 1 1 auto;
                min-width: 0;
            }

            .progress-heading {
                margin: 0 0 6px;
                font-size: 15px;
                font-weight: 700;
            }

            .progress-heading-row {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }

            .progress-copy {
                margin: 0;
                font-size: 12px;
                line-height: 1.5;
                color: var(--muted);
            }

            .progress-summary {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 10px;
                flex: 0 1 760px;
                min-width: min(100%, 620px);
                width: min(100%, 760px);
            }

            .progress-summary-card {
                padding: 12px 14px;
                border: 1px solid #e3ebf8;
                border-radius: 12px;
                background: linear-gradient(180deg, #fbfdff 0%, #f5f9ff 100%);
                text-align: right;
                min-width: 0;
            }

            .progress-summary-label {
                display: block;
                margin-bottom: 6px;
                font-size: 10px;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: #8190aa;
            }

            .progress-summary-value {
                display: block;
                font-size: 24px;
                font-weight: 700;
                letter-spacing: -0.04em;
                color: #183f68;
            }

            .progress-summary-detail {
                margin-top: 5px;
                font-size: 12px;
                color: var(--muted);
            }

            .progress-estimator-meta {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
                margin-top: 10px;
            }

            .estimator-pill {
                display: inline-flex;
                align-items: center;
                padding: 6px 10px;
                border-radius: 999px;
                background: #f1f6ff;
                color: #335b8d;
                font-size: 11px;
                font-weight: 700;
            }

            .estimator-pill.low {
                background: #fff5e8;
                color: #ab6a1d;
            }

            .estimator-pill.medium {
                background: #eef4ff;
                color: #31598b;
            }

            .estimator-pill.high {
                background: #e8f6ee;
                color: #2a764f;
            }

            .progress-bar {
                position: relative;
                height: 10px;
                overflow: hidden;
                border-radius: 999px;
                background: #edf3ff;
            }

            .progress-fill {
                height: 100%;
                width: 0%;
                border-radius: inherit;
                background: linear-gradient(90deg, #2a6aa4 0%, #4f8fc7 100%);
                transition: width 220ms ease;
            }

            .progress-stats {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 10px;
                margin-top: 12px;
            }

            .progress-stat {
                padding: 12px;
                border: 1px solid #e6edf9;
                border-radius: 10px;
                background: #f9fbff;
            }

            .progress-stat-label {
                display: block;
                margin-bottom: 5px;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: #7b8aa4;
            }

            .progress-stat-value {
                font-size: 20px;
                font-weight: 700;
                color: #173a60;
            }

            .image-queue {
                display: grid;
                gap: 10px;
                margin-top: 14px;
            }

            .image-queue-item {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 10px 14px;
                align-items: center;
                padding: 12px 14px;
                border: 1px solid #e6edf9;
                border-radius: 10px;
                background: #ffffff;
            }

            .image-queue-title {
                display: flex;
                align-items: center;
                gap: 10px;
                min-width: 0;
            }

            .image-queue-dot {
                flex: 0 0 auto;
                width: 10px;
                height: 10px;
                border-radius: 999px;
                background: #b7c4d9;
            }

            .image-queue-dot.queued {
                background: #8aa5c7;
            }

            .image-queue-dot.processing {
                background: #2f76ba;
                box-shadow: 0 0 0 5px rgba(47, 118, 186, 0.14);
            }

            .image-queue-dot.completed {
                background: #2e8a5a;
            }

            .image-queue-dot.failed {
                background: #d26b5a;
            }

            .image-queue-name {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: 13px;
                font-weight: 700;
                color: #173a60;
            }

            .image-queue-detail {
                margin-top: 4px;
                font-size: 12px;
                color: var(--muted);
            }

            .image-queue-status {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 112px;
                padding: 7px 10px;
                border-radius: 999px;
                background: #eef4ff;
                color: #31598b;
                font-size: 11px;
                font-weight: 700;
                text-transform: capitalize;
            }

            .image-queue-status.processing,
            .image-queue-status.queued {
                background: #edf4ff;
                color: #255486;
            }

            .image-queue-status.completed {
                background: #e8f6ee;
                color: #2a764f;
            }

            .image-queue-status.failed {
                background: #fff1ef;
                color: #b6534a;
            }

            .section-card {
                padding: clamp(14px, 1.35vw, 18px) clamp(14px, 1.35vw, 18px) clamp(16px, 1.7vw, 20px);
            }

            .section-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 14px;
                margin-bottom: 12px;
                flex-wrap: wrap;
            }

            .section-heading {
                margin: 0;
                font-size: 15px;
                font-weight: 700;
            }

            .section-heading-group {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                min-width: 0;
            }

            .section-badges {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            .section-badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 7px 12px;
                border: 1px solid #dde7f6;
                border-radius: 999px;
                background: #f4f8ff;
                color: #294a71;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.02em;
            }

            .section-badge-label {
                color: #7a8aa6;
                font-size: 10px;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
            }

            .section-badge-value {
                color: #24476d;
                font-size: 12px;
                font-weight: 700;
            }

            .table-action {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                min-height: 34px;
                padding: 0 14px;
                border-radius: 5px;
                background: linear-gradient(180deg, #26629a 0%, #1d527f 100%);
                color: #ffffff;
                font-size: 12px;
                font-weight: 700;
            }

            .table-action:disabled {
                cursor: not-allowed;
                opacity: 0.55;
            }

            .table-wrap {
                overflow: hidden;
                border: 1px solid #e7edf7;
                border-radius: 4px;
                background: #ffffff;
            }

            .table-loading {
                display: none;
                padding: 18px 16px 0;
                font-size: 12px;
                color: var(--muted);
            }

            .table-loading.active {
                display: block;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            thead th {
                padding: 13px 16px;
                background: #f4f7fd;
                border-bottom: 1px solid #e7edf7;
                text-align: left;
                font-size: 10px;
                font-weight: 700;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: #8190aa;
            }

            tbody td {
                padding: 16px;
                border-bottom: 1px solid #eef3fb;
                font-size: 13px;
            }

            tbody tr:last-child td {
                border-bottom: 0;
            }

            .lead-name {
                font-weight: 700;
                color: #173a60;
            }

            .lead-meta {
                margin-top: 4px;
                font-size: 12px;
                color: var(--muted);
            }

            .confidence-pill {
                display: inline-flex;
                align-items: center;
                min-width: 68px;
                justify-content: center;
                padding: 6px 10px;
                border-radius: 999px;
                background: #ebf5ef;
                color: #26704c;
                font-size: 11px;
                font-weight: 700;
            }

            .review-pill {
                display: inline-flex;
                align-items: center;
                padding: 6px 10px;
                border-radius: 999px;
                background: #eef4ff;
                color: #31598b;
                font-size: 11px;
                font-weight: 700;
                text-transform: capitalize;
            }

            .saved-pill {
                display: inline-flex;
                align-items: center;
                padding: 6px 10px;
                border-radius: 999px;
                background: #e8f6ee;
                color: #2a764f;
                font-size: 11px;
                font-weight: 700;
            }

            .empty-row td {
                padding: clamp(34px, 5vw, 52px) 14px clamp(38px, 5vw, 56px);
                text-align: center;
            }

            .empty-state {
                max-width: 360px;
                margin: 0 auto;
            }

            .empty-icon-stack {
                position: relative;
                width: 44px;
                height: 44px;
                margin: 0 auto 16px;
            }

            .empty-icon-main {
                width: 44px;
                height: 44px;
                display: grid;
                place-items: center;
                border-radius: 10px;
                background: #edf4ff;
                color: #95a6c5;
            }

            .empty-icon-badge {
                position: absolute;
                right: -2px;
                bottom: -2px;
                width: 16px;
                height: 16px;
                display: grid;
                place-items: center;
                border: 2px solid #ffffff;
                border-radius: 999px;
                background: #fff3f0;
                color: #d26b5a;
            }

            .empty-title {
                margin: 0 0 10px;
                font-size: 15px;
                font-weight: 700;
            }

            .empty-copy {
                margin: 0;
                font-size: 12px;
                line-height: 1.6;
                color: var(--muted);
            }

            @media (max-width: 760px) {
                .page {
                    width: calc(100% - 10px);
                    padding: 12px;
                }

                .title {
                    font-size: 30px;
                }

                .topbar,
                .section-header {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .section-heading-group {
                    width: 100%;
                }

                .tabs {
                    width: 100%;
                }

                .tab {
                    flex: 1 1 0;
                }

                .upload-panel {
                    padding: 12px;
                }

                .dropzone {
                    min-height: 210px;
                }

                .dropzone-inner {
                    width: 100%;
                    padding: 14px;
                }

                .upload-title {
                    font-size: 25px;
                }

                .upload-copy {
                    font-size: 13px;
                    max-width: 100%;
                }

                .selected-files {
                    width: 100%;
                }

                .selected-file {
                    grid-template-columns: auto minmax(0, 1fr);
                }

                .selected-file-remove {
                    grid-column: 2;
                    justify-self: end;
                    margin-top: -2px;
                }

                .progress-header {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .progress-summary {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    width: 100%;
                    min-width: 0;
                }

                .progress-stats {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .image-queue-item {
                    grid-template-columns: minmax(0, 1fr);
                }

                .table-wrap {
                    overflow-x: auto;
                }

                table {
                    min-width: 620px;
                }
            }
        </style>
    </head>
    <body>
        <main class="page">
            <header class="topbar">
                <div>
                    <h3 class="title">Lead Extraction</h3>
                </div>

                <div class="tabs" aria-label="Page navigation">
                    <button type="button" class="tab active">Extraction</button>
                    <button type="button" class="tab">Workspace</button>
                </div>
            </header>

            <section class="panel upload-panel">
                <label class="dropzone" id="dropzone" for="lead-images">
                    <input class="file-input" id="lead-images" type="file" accept="image/*" multiple>

                    <div class="dropzone-inner">
                        <div class="icon-box" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 12L12 8L16 12" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M12 8V16" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                                <path d="M7.75 21H16.25C18.7353 21 20 19.7353 20 17.25V6.75C20 4.26472 18.7353 3 16.25 3H7.75C5.26472 3 4 4.26472 4 6.75V17.25C4 19.7353 5.26472 21 7.75 21Z" stroke="currentColor" stroke-width="1.75"/>
                            </svg>
                        </div>

                        <h2 class="upload-title">Import Source Data</h2>
                        <p class="upload-copy" id="upload-copy">
                            Upload WhatsApp screenshots or lead images. The system will extract contacts, confidence scores, and review-ready records.
                        </p>

                        <div>
                            <button class="button-primary" id="browse-button" type="button">Browse Files</button>
                        </div>

                        <ul class="selected-files" id="selected-files" aria-live="polite"></ul>
                    </div>
                </label>
            </section>

            <section class="panel progress-panel" id="progress-panel" aria-live="polite">
                <div class="progress-header">
                    <div>
                        <div class="progress-heading-row">
                            <h2 class="progress-heading" id="progress-heading">Processing Queue</h2>
                            <span class="status-chip idle" id="progress-state-chip">idle</span>
                        </div>
                        <p class="progress-copy" id="progress-copy">Upload screenshots to start a batch and track each image while extraction runs.</p>
                        <div class="progress-estimator-meta">
                            <span class="estimator-pill low" id="progress-confidence">Low confidence</span>
                            <span class="estimator-pill" id="progress-basis">Historical basis</span>
                        </div>
                    </div>
                    <div class="progress-summary">
                        <div class="progress-summary-card">
                            <span class="progress-summary-label">Progress</span>
                            <span class="progress-summary-value" id="progress-percent">0%</span>
                            <div class="progress-summary-detail" id="progress-ratio">0 of 0 images</div>
                        </div>
                        <div class="progress-summary-card">
                            <span class="progress-summary-label">ETA</span>
                            <span class="progress-summary-value" id="progress-eta">--</span>
                            <div class="progress-summary-detail" id="progress-eta-detail">Estimated completion window</div>
                        </div>
                        <div class="progress-summary-card">
                            <span class="progress-summary-label">Elapsed</span>
                            <span class="progress-summary-value" id="progress-elapsed">0s</span>
                            <div class="progress-summary-detail" id="progress-elapsed-detail">Measured from first worker start</div>
                        </div>
                    </div>
                </div>

                <div class="progress-bar" aria-hidden="true">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>

                <div class="progress-stats">
                    <div class="progress-stat">
                        <span class="progress-stat-label">Queued</span>
                        <span class="progress-stat-value" id="queued-count">0</span>
                    </div>
                    <div class="progress-stat">
                        <span class="progress-stat-label">Processing</span>
                        <span class="progress-stat-value" id="processing-count">0</span>
                    </div>
                    <div class="progress-stat">
                        <span class="progress-stat-label">Completed</span>
                        <span class="progress-stat-value" id="completed-count">0</span>
                    </div>
                    <div class="progress-stat">
                        <span class="progress-stat-label">Failed</span>
                        <span class="progress-stat-value" id="failed-count">0</span>
                    </div>
                </div>

                <div class="image-queue" id="image-queue"></div>
            </section>

            <section class="panel section-card">
                <div class="section-header">
                    <div class="section-heading-group">
                        <h2 class="section-heading">Extracted Data</h2>
                        <div class="section-badges" aria-live="polite">
                            <div class="section-badge">
                                <span class="section-badge-label">Leads</span>
                                <span class="section-badge-value" id="results-lead-count">0</span>
                            </div>
                            <div class="section-badge">
                                <span class="section-badge-label">Avg Confidence</span>
                                <span class="section-badge-value" id="results-confidence-average">N/A</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="table-action" id="commit-leads-button" disabled>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M12 5V19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        Add to Leads
                    </button>
                </div>

                <div class="table-loading" id="table-loading">Waiting for extraction jobs to finish.</div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Confidence</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="results-body">
                            <tr class="empty-row">
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="empty-icon-stack" aria-hidden="true">
                                            <div class="empty-icon-main">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M4 19V8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                                    <path d="M10 19V11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                                    <path d="M16 19V5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                                    <path d="M3 19.5H20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                                </svg>
                                            </div>
                                            <div class="empty-icon-badge">
                                                <svg width="8" height="8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                    <path d="M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                </svg>
                                            </div>
                                        </div>

                                        <h3 class="empty-title">No leads extracted yet</h3>
                                        <p class="empty-copy">
                                            Once you upload a document, your extracted leads will be tabulated here with intent scoring and verification status.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <script>
            const input = document.getElementById('lead-images');
            const dropzone = document.getElementById('dropzone');
            const browseButton = document.getElementById('browse-button');
            const uploadCopy = document.getElementById('upload-copy');
            const selectedFiles = document.getElementById('selected-files');
            const resultsBody = document.getElementById('results-body');
            const tableLoading = document.getElementById('table-loading');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const commitLeadsButton = document.getElementById('commit-leads-button');
            const progressPanel = document.getElementById('progress-panel');
            const progressHeading = document.getElementById('progress-heading');
            const progressStateChip = document.getElementById('progress-state-chip');
            const progressCopy = document.getElementById('progress-copy');
            const progressConfidence = document.getElementById('progress-confidence');
            const progressBasis = document.getElementById('progress-basis');
            const progressPercent = document.getElementById('progress-percent');
            const progressRatio = document.getElementById('progress-ratio');
            const progressEta = document.getElementById('progress-eta');
            const progressEtaDetail = document.getElementById('progress-eta-detail');
            const progressElapsed = document.getElementById('progress-elapsed');
            const progressElapsedDetail = document.getElementById('progress-elapsed-detail');
            const progressFill = document.getElementById('progress-fill');
            const queuedCount = document.getElementById('queued-count');
            const processingCount = document.getElementById('processing-count');
            const completedCount = document.getElementById('completed-count');
            const failedCount = document.getElementById('failed-count');
            const imageQueue = document.getElementById('image-queue');
            const resultsLeadCount = document.getElementById('results-lead-count');
            const resultsConfidenceAverage = document.getElementById('results-confidence-average');

            let activeBatchId = null;
            let pollingHandle = null;
            let activeBatchData = null;
            let latestStatusDetail = 'Upload screenshots to start a batch and track each image while extraction runs.';
            let removingImageId = null;

            const emptyStateMarkup = `
                <tr class="empty-row">
                    <td colspan="4">
                        <div class="empty-state">
                            <div class="empty-icon-stack" aria-hidden="true">
                                <div class="empty-icon-main">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4 19V8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                        <path d="M10 19V11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                        <path d="M16 19V5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                        <path d="M3 19.5H20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                    </svg>
                                </div>
                                <div class="empty-icon-badge">
                                    <svg width="8" height="8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="empty-title">No leads extracted yet</h3>
                            <p class="empty-copy">Once you upload a document, your extracted leads will be tabulated here with intent scoring and verification status.</p>
                        </div>
                    </td>
                </tr>`;

            const setStatus = (headline, detail, tone) => {
                progressPanel.classList.add('active');
                progressHeading.textContent = headline;
                progressCopy.textContent = detail;
                const toneLabels = {
                    uploading: 'Uploading',
                    saving: 'Saving',
                    error: 'Needs attention',
                    failed: 'Needs attention',
                    saved: 'Saved',
                    idle: 'Idle',
                };
                progressStateChip.textContent = toneLabels[tone] || formatBatchStateLabel(tone);
                progressStateChip.className = `status-chip ${tone}`;
                latestStatusDetail = detail;
            };

            const formatBatchStateLabel = (status) => {
                if (!status) {
                    return 'queued';
                }

                return status.replaceAll('_', ' ');
            };

            const getImageStatusCopy = (image) => {
                if (image.status === 'completed') {
                    const recordCount = image.extracted_records ?? 0;
                    return `${recordCount} lead${recordCount === 1 ? '' : 's'} detected`;
                }

                if (image.status === 'failed') {
                    return image.error_message || 'Extraction failed for this image';
                }

                if (image.status === 'processing') {
                    return 'OCR and parsing are running on this screenshot';
                }

                return 'Waiting in queue for the next worker slot';
            };

            const isCompletedBatch = (status) => ['completed', 'completed_with_failures'].includes(status);

            const formatEstimatorBasis = (basis, status) => {
                if (isCompletedBatch(status)) {
                    return 'Runtime measured from this batch';
                }

                if (!basis) {
                    return 'Estimate source: historical model';
                }

                const labels = {
                    historical: 'Estimate source: historical model',
                    batch: 'Estimate source: current batch',
                    blended: 'Estimate source: batch + history',
                };

                return labels[basis] || basis.replaceAll('_', ' ');
            };

            const formatEstimatorConfidence = (confidence, status) => {
                if (isCompletedBatch(status)) {
                    return {
                        label: 'Final runtime',
                        tone: 'high',
                    };
                }

                const normalized = (confidence || 'low').toLowerCase();

                return {
                    label: `ETA confidence: ${normalized}`,
                    tone: normalized,
                };
            };

            const formatProgressIndicator = ({ status, queued, processing, failed, totalLeads, hasUnsavedLeads }) => {
                if (status === 'failed') {
                    return {
                        label: 'Needs attention',
                        tone: 'error',
                    };
                }

                if (processing > 0) {
                    return {
                        label: `${processing} active`,
                        tone: 'processing',
                    };
                }

                if (queued > 0) {
                    return {
                        label: `${queued} queued`,
                        tone: 'queued',
                    };
                }

                if (failed > 0 && isCompletedBatch(status)) {
                    return {
                        label: `${failed} failed`,
                        tone: 'error',
                    };
                }

                if (isCompletedBatch(status) && hasUnsavedLeads) {
                    return {
                        label: `${totalLeads} leads ready`,
                        tone: 'completed',
                    };
                }

                if (isCompletedBatch(status)) {
                    return {
                        label: 'Completed',
                        tone: 'completed',
                    };
                }

                return {
                    label: 'Idle',
                    tone: 'idle',
                };
            };

            const formatBatchHeading = (batch) => {
                if (batch.status === 'failed') {
                    return 'Batch failed';
                }

                if (isCompletedBatch(batch.status)) {
                    return 'Extraction batch completed';
                }

                return 'Extraction batch in progress';
            };

            const renderProcessingQueue = (batch) => {
                const images = batch.images || [];
                const queued = images.filter((image) => image.status === 'queued').length;
                const processing = images.filter((image) => image.status === 'processing').length;
                const completed = images.filter((image) => image.status === 'completed').length;
                const failed = images.filter((image) => image.status === 'failed').length;
                const totalLeads = images.reduce((total, image) => total + (image.leads || []).length, 0);
                const hasUnsavedLeads = images.some((image) => (image.leads || []).some((lead) => !lead.is_saved));
                const percent = batch.total_images ? Math.round((batch.processed_images / batch.total_images) * 100) : 0;
                const activeNames = images
                    .filter((image) => image.status === 'processing')
                    .map((image) => image.original_name)
                    .slice(0, 2);

                progressPanel.classList.toggle('active', images.length > 0);
                progressPercent.textContent = `${percent}%`;
                progressRatio.textContent = `${batch.processed_images}/${batch.total_images} images finished`;
                progressEta.textContent = batch.eta_label || 'Estimating time left...';
                progressEtaDetail.textContent = batch.status === 'completed' || batch.status === 'completed_with_failures'
                    ? 'Batch fully processed'
                    : `Raw estimate ${batch.eta_seconds_raw ?? batch.eta_seconds_display ?? 0}s`;
                progressElapsed.textContent = batch.elapsed_label ? batch.elapsed_label.replace(' elapsed', '') : '0s';
                progressElapsedDetail.textContent = batch.status === 'completed' || batch.status === 'completed_with_failures'
                    ? 'Actual total processing time'
                    : 'Measured from first worker start';
                const progressIndicator = formatProgressIndicator({
                    status: batch.status,
                    queued,
                    processing,
                    failed,
                    totalLeads,
                    hasUnsavedLeads,
                });
                progressStateChip.textContent = progressIndicator.label;
                progressStateChip.className = `status-chip ${progressIndicator.tone}`;
                const estimatorConfidence = formatEstimatorConfidence(batch.eta_confidence, batch.status);
                progressConfidence.textContent = estimatorConfidence.label;
                progressConfidence.className = `estimator-pill ${estimatorConfidence.tone}`;
                progressBasis.textContent = formatEstimatorBasis(batch.eta_basis, batch.status);
                progressFill.style.width = `${percent}%`;
                queuedCount.textContent = String(queued);
                processingCount.textContent = String(processing);
                completedCount.textContent = String(completed);
                failedCount.textContent = String(failed);

                if (processing > 0 && activeNames.length) {
                    progressCopy.textContent = `Currently processing ${activeNames.join(', ')}${processing > activeNames.length ? ' and more' : ''}.`;
                } else if (queued > 0) {
                    progressCopy.textContent = `${queued} image${queued === 1 ? '' : 's'} still waiting in queue.`;
                } else if (failed > 0) {
                    progressCopy.textContent = 'Batch finished with some failed screenshots that may need to be retried.';
                } else if (completed > 0) {
                    progressCopy.textContent = 'All screenshots have finished processing.';
                } else {
                    progressCopy.textContent = latestStatusDetail;
                }

                imageQueue.innerHTML = images.map((image) => `
                    <article class="image-queue-item">
                        <div>
                            <div class="image-queue-title">
                                <span class="image-queue-dot ${image.status || 'queued'}" aria-hidden="true"></span>
                                <div class="image-queue-name">${image.original_name}</div>
                            </div>
                            <div class="image-queue-detail">${getImageStatusCopy(image)}</div>
                        </div>
                        <span class="image-queue-status ${image.status || 'queued'}">${formatBatchStateLabel(image.status || 'queued')}</span>
                    </article>
                `).join('');
            };

            const renderEmptyState = () => {
                resultsBody.innerHTML = emptyStateMarkup;
                resultsLeadCount.textContent = '0';
                resultsConfidenceAverage.textContent = 'N/A';
            };

            const renderSelectedBatchFiles = (batch) => {
                const images = batch?.images || [];
                const canRemove = !batch?.persisted_batch_id;

                selectedFiles.innerHTML = '';

                if (!images.length) {
                    uploadCopy.textContent = 'Upload WhatsApp screenshots or lead images. The system will extract contacts, confidence scores, and review-ready records.';
                    return;
                }

                uploadCopy.textContent = `${images.length} file${images.length > 1 ? 's' : ''} in the current extraction batch.`;

                images.forEach((image) => {
                    const item = document.createElement('li');
                    item.className = 'selected-file';
                    item.innerHTML = `
                        <div class="selected-file-icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 7H16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                <path d="M8 12H13" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                <path d="M8 17H12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                <path d="M7.75 21H16.25C18.7353 21 20 19.7353 20 17.25V6.75C20 4.26472 18.7353 3 16.25 3H7.75C5.26472 3 4 4.26472 4 6.75V17.25C4 19.7353 5.26472 21 7.75 21Z" stroke="currentColor" stroke-width="1.7"/>
                            </svg>
                        </div>
                        <div class="selected-file-content">
                            <span class="selected-file-name">${image.original_name}</span>
                            <div class="selected-file-meta">
                                <span class="selected-file-size">${Math.max(1, Math.round((image.size ?? 0) / 1024))} KB</span>
                                <span>Queued for extraction</span>
                            </div>
                        </div>
                        ${canRemove ? `<button type="button" class="selected-file-remove" data-image-id="${image.id}" ${removingImageId === image.id ? 'disabled' : ''} aria-label="Remove ${image.original_name}">&times;</button>` : ''}
                    `;
                    selectedFiles.appendChild(item);
                });
            };

            const formatConfidence = (value) => {
                if (value == null) {
                    return 'N/A';
                }

                return `${Math.round(value)}%`;
            };

            const updateResultsSummary = (leads) => {
                resultsLeadCount.textContent = String(leads.length);

                const scoredLeads = leads.filter((lead) => lead.confidence_score != null && Number.isFinite(Number(lead.confidence_score)));

                if (!scoredLeads.length) {
                    resultsConfidenceAverage.textContent = 'N/A';
                    return;
                }

                const averageConfidence = scoredLeads.reduce((total, lead) => total + Number(lead.confidence_score), 0) / scoredLeads.length;
                resultsConfidenceAverage.textContent = formatConfidence(averageConfidence);
            };

            const renderBatchResults = (batch) => {
                const leads = (batch.images || []).flatMap((image) =>
                    (image.leads || []).map((lead) => ({
                        ...lead,
                        image,
                    }))
                );

                updateResultsSummary(leads);

                if (!leads.length) {
                    renderEmptyState();
                    return;
                }

                resultsBody.innerHTML = leads.map((lead) => `
                    <tr>
                        <td>
                            <div class="lead-name">${lead.name ?? 'Unnamed lead'}</div>
                            <div class="lead-meta">${lead.image.original_name}</div>
                        </td>
                        <td>${lead.normalized_phone_number || lead.phone_number || 'No phone detected'}</td>
                        <td><span class="confidence-pill">${formatConfidence(lead.confidence_score)}</span></td>
                        <td>${lead.is_saved ? '<span class="saved-pill">Saved</span>' : `<span class="review-pill">${lead.review_status || 'pending_review'}</span>`}</td>
                    </tr>
                `).join('');
            };

            const updateBatchState = (batch) => {
                activeBatchData = batch;
                const completed = ['completed', 'completed_with_failures'].includes(batch.status);
                const processing = (batch.images || []).filter((image) => image.status === 'processing').length;
                const queued = (batch.images || []).filter((image) => image.status === 'queued').length;
                const hasUnsavedLeads = (batch.images || []).some((image) => (image.leads || []).some((lead) => !lead.is_saved));

                let detail = `${batch.processed_images}/${batch.total_images} image jobs processed`;

                if (!completed && processing > 0) {
                    detail = `${processing} image${processing === 1 ? '' : 's'} processing, ${queued} waiting in queue.`;
                } else if (!completed && queued > 0) {
                    detail = `${queued} image${queued === 1 ? '' : 's'} waiting for worker pickup.`;
                }

                setStatus(formatBatchHeading(batch), detail, batch.status);

                tableLoading.classList.toggle('active', !completed);
                commitLeadsButton.disabled = !completed || !hasUnsavedLeads;
                renderSelectedBatchFiles(batch);
                renderProcessingQueue(batch);
                renderBatchResults(batch);

                if ((completed || (batch.total_images ?? 0) === 0) && pollingHandle) {
                    clearInterval(pollingHandle);
                    pollingHandle = null;
                }
            };

            const pollBatch = async (batchId) => {
                const response = await fetch(`{{ url('/extraction-batches') }}/${batchId}`, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Unable to fetch extraction batch status.');
                }

                const payload = await response.json();
                updateBatchState(payload.data);
            };

            const startPolling = (batchId) => {
                if (pollingHandle) {
                    clearInterval(pollingHandle);
                }

                pollingHandle = setInterval(() => {
                    pollBatch(batchId).catch((error) => {
                        setStatus('Unable to refresh batch status', error.message, 'error');
                    });
                }, 2000);
            };

            const submitBatch = async (files) => {
                if (!files.length) {
                    return;
                }

                const formData = new FormData();

                Array.from(files).forEach((file) => {
                    formData.append('images[]', file);
                });

                setStatus('Uploading screenshots', `Sending ${files.length} file${files.length > 1 ? 's' : ''} to Laravel.`, 'uploading');
                tableLoading.classList.add('active');
                renderEmptyState();

                const response = await fetch(`{{ route('extraction-batches.store') }}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });

                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.message || 'Unable to create extraction batch.');
                }

                activeBatchId = payload.data.id;
                updateBatchState(payload.data);
                startPolling(activeBatchId);
                await pollBatch(activeBatchId);
            };

            const commitLeads = async () => {
                if (!activeBatchId) {
                    return;
                }

                commitLeadsButton.disabled = true;
                setStatus('Saving extracted leads', 'Persisting the current batch results into the extracted leads table.', 'saving');

                const response = await fetch(`{{ url('/extraction-batches') }}/${activeBatchId}/commit-leads`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.message || 'Unable to save extracted leads.');
                }

                updateBatchState(payload.data);
                setStatus('Extracted leads saved', `${payload.created_count ?? 0} lead${(payload.created_count ?? 0) === 1 ? '' : 's'} stored in the database.`, 'saved');
            };

            const removeBatchImage = async (imageId) => {
                if (!activeBatchId || !imageId) {
                    return;
                }

                removingImageId = imageId;
                if (activeBatchData) {
                    renderSelectedBatchFiles(activeBatchData);
                }

                const response = await fetch(`{{ url('/extraction-batches') }}/${activeBatchId}/images/${imageId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const payload = await response.json();
                removingImageId = null;

                if (!response.ok) {
                    throw new Error(payload.message || 'Unable to remove image from extraction batch.');
                }

                updateBatchState(payload.data);

                if ((payload.data.total_images ?? 0) > 0) {
                    setStatus('Image removed', 'The screenshot and its extracted leads were removed from the current batch.', 'queued');
                }
            };

            const renderFiles = (files) => {
                selectedFiles.innerHTML = '';

                if (!files.length) {
                    uploadCopy.textContent = 'Upload your PDF reports, CSV logs, or raw text files. Our ledger will automatically parse and classify lead data.';
                    return;
                }

                uploadCopy.textContent = `${files.length} file${files.length > 1 ? 's' : ''} selected for import preview.`;

                Array.from(files).forEach((file) => {
                    const item = document.createElement('li');
                    item.className = 'selected-file';
                    item.innerHTML = `<strong>${Math.max(1, Math.round(file.size / 1024))} KB</strong><span>${file.name}</span>`;
                    selectedFiles.appendChild(item);
                });
            };
            const shouldAbandonActiveBatch = () => {
                if (!activeBatchId || !activeBatchData) {
                    return false;
                }

                if (activeBatchData.persisted_batch_id) {
                    return false;
                }

                return !isCompletedBatch(activeBatchData.status);
            };

            const abandonActiveBatch = () => {
                if (!shouldAbandonActiveBatch()) {
                    return;
                }

                fetch(`{{ url('/extraction-batches') }}/${activeBatchId}/abandon`, {
                    method: 'POST',
                    keepalive: true,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }).catch(() => {
                    // Ignore unload-time failures; the server remains the source of truth.
                });
            };

            browseButton.addEventListener('click', () => input.click());

            input.addEventListener('change', async (event) => {
                renderFiles(event.target.files);

                if (event.target.files?.length) {
                    try {
                        await submitBatch(event.target.files);
                    } catch (error) {
                        setStatus('Upload failed', error.message, 'error');
                        tableLoading.classList.remove('active');
                    }
                }
            });

            ['dragenter', 'dragover'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.remove('dragover');
                });
            });

            dropzone.addEventListener('drop', (event) => {
                const files = event.dataTransfer?.files;

                if (!files || !files.length) {
                    return;
                }

                input.files = files;
                renderFiles(files);

                submitBatch(files).catch((error) => {
                    setStatus('Upload failed', error.message, 'error');
                    tableLoading.classList.remove('active');
                });
            });

            selectedFiles.addEventListener('click', (event) => {
                const button = event.target.closest('.selected-file-remove');

                if (!button) {
                    return;
                }

                removeBatchImage(button.dataset.imageId).catch((error) => {
                    removingImageId = null;
                    if (activeBatchData) {
                        renderSelectedBatchFiles(activeBatchData);
                    }
                    setStatus('Remove failed', error.message, 'error');
                });
            });

            commitLeadsButton.addEventListener('click', () => {
                commitLeads().catch((error) => {
                    setStatus('Save failed', error.message, 'error');
                    if (activeBatchData) {
                        updateBatchState(activeBatchData);
                    }
                });
            });
            window.addEventListener('pagehide', abandonActiveBatch);

            renderEmptyState();
            renderProcessingQueue({
                images: [],
                total_images: 0,
                processed_images: 0,
                status: 'idle',
            });
        </script>
    </body>
</html>