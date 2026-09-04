# Config nginx

Dua host, satu aplikasi:

| Host | Root | Melayani |
|---|---|---|
| `fed-bo.pborado.com` | `/home/oredo/dev_html/dwf-backend/public` | Backoffice — semuanya |
| `fed-api.pborado.com` | idem | **Hanya `/api`**, sisanya 404 |

## Memasang

```bash
sudo cp fed-bo.pborado.com.conf  /etc/nginx/sites-available/
sudo cp fed-api.pborado.com.conf /etc/nginx/sites-available/
sudo ln -s ../sites-available/fed-bo.pborado.com.conf  /etc/nginx/sites-enabled/
sudo ln -s ../sites-available/fed-api.pborado.com.conf /etc/nginx/sites-enabled/

ls /run/php/                       # cocokkan versi soket di kedua berkas
sudo nginx -t && sudo systemctl reload nginx

sudo certbot --nginx -d fed-bo.pborado.com -d fed-api.pborado.com
```

TLS sengaja tidak ditulis tangan — certbot yang menambahkannya, dan dua sumber
untuk hal yang sama adalah dua tempat yang bisa berbeda pendapat.

## Yang harus cocok di `.env`

```dotenv
APP_URL=https://fed-bo.pborado.com
CORS_ALLOWED_ORIGINS=https://dwf-domino.org
```

**`APP_URL` menunjuk host BACKOFFICE, bukan host API.** Ia yang membangun URL
gambar dan URL unduhan dokumen di response API. Konsekuensinya: situs publik
mengambil gambar dari `fed-bo.pborado.com`, bukan dari `fed-api`. Itu bekerja,
dan bisa dipindah kapan saja dengan `MEDIA_URL` (lihat `docs/PRODUCTION.md` §3)
— tapi jangan menyetel `APP_URL` ke host API demi kerapian: redirect login,
tautan email undangan, dan reset sandi semuanya dibangun dari nilai itu, dan
semuanya akan menunjuk host yang membalas 404.

`CORS_ALLOWED_ORIGINS` berisi domain SITUS PUBLIK — yang memanggil, bukan yang
dipanggil. Kosong berarti setiap permintaan dari browser ditolak; `*` berarti
lubang yang tidak terlihat di mana pun.

## Menguji setelah reload

```bash
curl -si https://fed-api.pborado.com/api/v1/news | head -1      # 200
curl -si https://fed-api.pborado.com/login       | head -1      # 404  ← yang penting
curl -si https://fed-api.pborado.com/index.php   | head -1      # 404  ← `internal`
curl -si https://fed-bo.pborado.com/login        | head -1      # 200
curl -si https://fed-bo.pborado.com/.env         | head -1      # 403/404
```

Baris kedua dan ketiga yang membuktikan pemisahannya benar-benar bekerja.
