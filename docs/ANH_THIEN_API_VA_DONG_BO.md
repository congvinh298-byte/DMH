# Anh Thien API va dong bo du an

## Nguon chinh

- Sua code tai: `C:\Users\pcpv\OneDrive\Desktop\DTH`
- Luu tru/backup tai: `D:\DTH`
- Hosting public dang dung remote path: `/public_html`

Khong xem OneDrive/D drive la hosting. Web `dienmayhieu.com` chi thay doi sau khi code tu thu muc chinh duoc upload len hosting.

## API tro ly Anh Thien

Endpoint public:

```text
POST /api_master.php?action=anh_thien_chat
```

Body JSON mau:

```json
{
  "message": "Khach hoi ve gia ve sinh may lanh",
  "service_type": "Tho dien lanh",
  "selected_service": "Ve sinh may lanh",
  "public_price": "165000",
  "address": "Lap Vo, Dong Thap"
}
```

Bien moi truong can co trong `.env` tren may chinh va hosting:

```text
OLLAMA_ENABLED=true
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=gemma4:12b
OLLAMA_CONNECT_TIMEOUT=2
OLLAMA_TIMEOUT=60
OLLAMA_KEEP_ALIVE=10m
OLLAMA_NUM_CTX=8192
OLLAMA_TOP_P=0.95
OLLAMA_TOP_K=64
GEMMA_TEMPERATURE=0.30
GEMMA_MAX_OUTPUT_TOKENS=520
GEMMA_THINKING_LEVEL=
GEMINI_API_KEY=
GEMINI_TEXT_MODEL=gemini-1.5-flash
HF_TOKEN=
HF_TEXT_MODEL=google/gemma-2-9b-it
```

Uu tien goi Ollama local voi model `gemma4:12b`. Neu Ollama chua chay, API se roi qua Gemini, sau do Hugging Face. Neu chua co provider nao, API tra loi mau an toan de web khong bi loi trang.

Tren hosting public, `127.0.0.1` la may chu hosting, khong phai may tinh cua em. Neu hosting khong cai Ollama, can giu Gemini/Hugging Face lam du phong, hoac doi `OLLAMA_BASE_URL` sang mot endpoint Ollama/VPS/tunnel rieng co bao mat.

## Lenh cai Ollama + Gemma 4 12B tren Windows

```powershell
powershell -ExecutionPolicy Bypass -File scripts\install_ollama_gemma4.ps1
```

Mac dinh script luu model vao `D:\Ollama\models` de tranh lam day o C. Muon doi noi luu:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\install_ollama_gemma4.ps1 -ModelDir "D:\Ollama\models"
```

Sau khi xong, test truc tiep:

```powershell
Invoke-RestMethod http://127.0.0.1:11434/api/tags
```

## Lenh chay local

```powershell
powershell -ExecutionPolicy Bypass -File scripts\local_web.ps1 -Port 8090
```

Mo:

```text
http://127.0.0.1:8090
```

## Lenh dong bo C sang D

Xem truoc file se copy:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\sync_storage.ps1
```

Copy that:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\sync_storage.ps1 -Apply
```

Lenh nay khong copy `.env`, `.vscode`, `.git`, log, upload va du lieu rieng tu.
