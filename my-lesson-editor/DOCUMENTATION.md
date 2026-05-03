# Dokumentasi Sistem Author → Book → Lesson  
## My Lesson Editor & Integrasi n8n

**Versi dokumen:** 1.0  
**Plugin utama yang dibahas:** My Lesson Editor (WordPress)  
**Konteks:** Alur kerja konten pendidikan dari profil **Author**, materi **Book**, hingga **Lesson**, dengan otomasi AI melalui **n8n**.

---

## 1. Ringkasan eksekutif

Platform ini memisahkan tiga lapisan konten:

| Lapisan | Peran | Custom Post Type (CPT) | Antarmuka editor |
|--------|--------|-------------------------|-------------------|
| **Author** | Identitas dan profil pengarang | `author_profile` | Plugin Author Editor (mis. *My Author Editor*) |
| **Book** | Buku / materi referensi | `book_profile` | Plugin *My Book Editor* — shortcode `[my_book_editor]` |
| **Lesson** | Pelajaran, rencana ajar, konten siap pakai | `lesson_profile` | Plugin *My Lesson Editor* — shortcode `[my_lesson_editor]` |

**Lesson Editor** menghubungkan lesson dengan author (dropdown), mengirim payload terstruktur ke **webhook n8n**, dan menampilkan hasil ke area pratinjau serta editor utama WordPress.

---

## 2. Prasyarat teknis

### 2.1 WordPress

- Instalasi WordPress stabil, HTTPS disarankan untuk cookie dan media.
- Pengguna yang mengakses editor lesson harus **login** dan memiliki kemampuan **`edit_posts`** (atau setara peran yang Anda konfigurasi).

### 2.2 Plugin yang relevan

| Plugin | Fungsi |
|--------|--------|
| **My Lesson Editor** | CPT lesson, taxonomy lesson, shortcode lesson, AJAX ke n8n. |
| **My Book Editor** | CPT book, shortcode buku (alur materi). |
| **My Author Editor** (varian terpasang di situs Anda) | CPT author, shortcode dashboard author. |

Pastikan plugin **aktif** dan tidak ada konflik nama shortcode.

### 2.3 n8n

- Workflow dengan node **Webhook** (method **POST**), **aktif**.
- URL webhook dikonfigurasi agar WordPress dapat memanggilnya dari server (bukan dari browser pengguna langsung ke n8n untuk otomasi utama — pemanggilan dari **PHP `wp_remote_post`**).

### 2.4 Konstanta webhook (disarankan)

Di **`wp-config.php`** (di atas baris `/* That's all, stop editing! */`):

```php
define( 'MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL', 'https://YOUR-N8N-HOST/webhook/YOUR-WEBHOOK-PATH' );
```

Jika tidak didefinisikan, plugin dapat memakai URL default internal; untuk produksi **wajib** menetapkan URL workflow lesson khusus Anda.

---

## 3. Glosarium data

### 3.1 Author (`author_profile`)

- **CPT:** `author_profile`
- **Taxonomi (contoh pada instalasi Author Editor):** `author_category`, `author_level`, `placement_category` — sesuaikan dengan plugin author Anda.
- **Penggunaan di Lesson:** Lesson Editor memuat daftar author dari post type `author_profile` (status *publish*). Nama yang tampil di payload n8n diambil dari **judul post** author terpilih.

**Tatacara (Author):**

1. Di WordPress admin, buka menu entitas **Author** / **Authors** sesuai label CPT Anda.
2. Buat atau edit **Author profile** — isi judul (nama tampilan), konten profil, dan meta yang dipakai organisasi Anda.
3. **Publish** agar author muncul di dropdown **Select an author** pada halaman Lesson Editor.

### 3.2 Book (`book_profile`)

- **CPT:** `book_profile` (My Book Editor)
- **Shortcode:** `[my_book_editor]` pada halaman / template yang diinginkan.
- **Relasi konseptual:** Buku mengikat materi ke pengarang dan metadata buku; lesson kemudian dapat merujuk konteks yang sama lewat judul buku / file referensi di panel AI.

**Tatacara (Book):**

1. Pasang halaman dengan shortcode **`[my_book_editor]`** (atau sesuai dokumentasi My Book Editor).
2. Buat profil buku: judul, deskripsi, penulis terkait (jika UI book editor mendukung pemilihan author), kategori/level sesuai plugin book.
3. Simpan sebagai *draft* atau *publish* sesuai alur QA Anda.
4. Saat menyusun **lesson**, gunakan field **Book / textbook title** dan opsional **upload file** di sidebar AI agar konteks generation selaras dengan buku yang sama.

### 3.3 Lesson (`lesson_profile`)

- **CPT:** `lesson_profile`
- **Shortcode:** `[my_lesson_editor]`
- **Taxonomi lesson:**  
  - `lesson_category`  
  - `lesson_level` (nilai umum di form: `beginner`, `intermediate`, `advanced`)  
  - `lesson_placement` (penempatan konten)
- **Meta penting:** `_lesson_author_id` — disimpan saat submit lesson jika author dipilih; dipakai saat memuat lesson untuk mengisi ulang dropdown author.

**Tatacara (Lesson) — alur pengguna:**

1. Buat halaman (mis. *Lesson Dashboard*) dan tambahkan blok shortcode: **`[my_lesson_editor]`**.
2. Buka halaman tersebut dengan akun yang punya hak mengedit.
3. **(Opsional)** Pilih **author** → klik **Submit** di baris author untuk memuat konteks (AJAX `my_lesson_editor_get_author_content`).
4. **(Opsional)** Pilih **lesson** yang sudah ada → **Submit** untuk memuat judul, subtitle, isi, cover, taxonomi, dan author terkait (`my_lesson_editor_get_lesson_content`).
5. Isi **Add a lesson** (judul baru) atau lanjutkan edit lesson terpilih.
6. Isi **Body content** (TinyMCE), **category**, **level**, **placement**, **cover** sesuai kebutuhan.
7. Gunakan **Action** (Save draft / Publish / dll.) dan **Publish** ke placement jika fitur itu dipakai.
8. Di sidebar **AI Content Generator**, isi konteks buku/chapter, centang **Select Content Types to Generate**, lalu **Start Automation Process** untuk mengirim payload ke n8n (lihat bagian 6).
9. Setelah sukses, gunakan **Load into main editor** atau **Save as new lesson draft** dari area pratinjau.

---

## 4. Tata letak antarmuka My Lesson Editor

### 4.1 Area utama — Lesson Content Editor

| Elemen | ID / nama (relevan) | Fungsi |
|--------|---------------------|--------|
| Pilih author | `selected_author_id` | Dropdown `author_profile`. |
| Submit author | `#load-author-content-btn` | Memuat data author (nama untuk konteks). |
| Pilih lesson | `selected_lesson_id` | Daftar `lesson_profile`. |
| Submit lesson | `#load-lesson-content-btn` | Memuat konten lesson ke form. |
| Judul lesson | `new_lesson_name` | Judul tampil / baru. |
| Subtitle | `lesson_subtitle` | Subtitle. |
| Cover | `lesson_cover_id`, `lesson_cover_url`, `.browse-lesson-cover` | Media WordPress. |
| Isi | `lesson_content` | Editor visual TinyMCE. |
| Kategori / level | `lesson_category`, `lesson_level` | Taxonomy slug & level. |
| Aksi simpan | `submit_lesson_action_select`, `submit_lesson_action_btn` | admin-post submit. |
| Placement | `placement_category`, tombol Publish placement | Taxonomy `lesson_placement`. |

### 4.2 Sidebar — AI Content Generator

| Bagian | Fungsi |
|--------|--------|
| **Reference and context** | Judul buku (`book_title`), chapter (`ai_chapter`), model AI (`llm_model_select`), upload file opsional (`book_upload`, max 4 MB). |
| **Select Content Types to Generate** | Checkbox: lesson plan, vocabulary, flashcards, quiz, midterm, final exam. |
| **Ringkasan pilihan** | `selected-count`, `selected-types` — diperbarui oleh JavaScript. |
| **n8n automation** | Tombol **Start Automation Process** — tidak memerlukan prompt manual di area utama. |
| **Output preview** | `final_generated_title`, `final_generated_content` — diisi dari respons n8n/WordPress. |
| **Optional: manual LLM prompt** | Tertutup di `<details>` — alur terpisah, bukan syarat untuk Start Automation. |

### 4.3 Sidebar — Generated Drafts Content

- Memilih judul lesson (`select_title_ce`) dan opsi draft (`select_draft_ce`, termasuk **Main lesson body**).
- **Load Content to Editor** memuat konten via AJAX `my_lesson_editor_load_draft_content`.

---

## 5. Alur data Author → Book → Lesson (operasional)

```
┌─────────────┐     ┌─────────────┐     ┌──────────────────────┐
│   Author    │     │    Book     │     │       Lesson         │
│ author_     │     │ book_       │     │ lesson_profile       │
│ profile     │     │ profile     │     │ + _lesson_author_id  │
└──────┬──────┘     └──────┬──────┘     └──────────┬───────────┘
       │                   │                        │
       │ dipilih di        │ judul/konteks        │ judul, level,
       │ dropdown lesson   │ diselaraskan         │ kategori, isi,
       │                   │ dengan lesson        │ automation AI
       └───────────────────┴────────────────────┘
```

**Rekomendasi QA:**

1. Pastikan author **publish** sebelum dipakai di lesson.
2. Samakan **nama buku** antara entri Book (referensi organisasi) dan field **Book / textbook title** di lesson AI bing ingin konsistensi pelacakan.
3. Setelah generate, tinjau **Output preview** sebelum **Load into main editor** atau simpan draft.

---

## 6. Integrasi n8n (Lesson automation)

### 6.1 Ringkasan teknis

- WordPress mengirim **POST JSON** ke URL `MY_LESSON_EDITOR_N8N_MAIN_WEBHOOK_URL`.
- Badan permintaan berbentuk **amplop (envelope)** agar ekspresi n8n konsisten:

```json
{
  "source": "my_lesson_editor_full_automation",
  "body": {
    "book_name": "string",
    "author_name": "string",
    "difficulty_level": "beginner|intermediate|advanced|…",
    "chapter": "string",
    "model": "string",
    "lesson_id": 0,
    "lesson_title": "string",
    "lesson_subtitle": "string",
    "lesson_category": "string",
    "tasks": {
      "lesson_plan": true,
      "vocabulary": false,
      "flashcards": false,
      "quiz": false,
      "midterm": false,
      "final_exam": false
    },
    "selected_tasks": [
      {
        "id": "quiz",
        "resolved_prompt": "string",
        "mc_qty": 10,
        "short_answer_qty": 4,
        "discussion_questions_count": 0
      }
    ],
    "textbook_filename": "optional",
    "textbook_base64": "optional"
  }
}
```

- **`tasks`:** pilihan checkbox pengguna.  
- **`selected_tasks`:** hanya entri yang `true`, dengan instruksi teks (`resolved_prompt`) dan kuantitas bila relevan (quiz/midterm/final).

### 6.2 Konfigurasi n8n

1. Buat workflow dengan trigger **Webhook** — method **POST**, path unik.
2. Di node berikutnya (mis. **Set** atau **Code**), referensi data dengan **`$json.body.*`** (sesuai amplop di atas).
3. Hubungkan ke node LLM; **User message** dan **System message** harus membaca field di **`$json.body`** (bukan struktur lama yang tidak dikirim WordPress).
4. Node akhir harus mengembalikan HTTP **2xx** dan body JSON yang dapat dibaca WordPress.

### 6.3 Format respons untuk WordPress

Plugin menerima salah satu bentuk berikut (diproses oleh `my_lesson_editor_extract_automation_output`):

- `title` + `content`, atau  
- `final_title` + `final_content`, atau  
- Nested di `data`, atau string JSON di field `output`.

Disarankan untuk konsistensi dengan prompt internal Anda:

```json
{
  "title": "Judul SEO hasil generate",
  "content": "Isi lengkap gabungan semua task…"
}
```

### 6.4 File referensi (textbook)

- Jika pengguna mengunggah file, WordPress dapat menyertakan **`textbook_base64`** + **`textbook_filename`** di `body`.
- **Jangan** memasukkan seluruh base64 ke prompt LLM (membebani token). Gunakan node pemrosesan file / ekstraksi teks di n8n jika diperlukan.

### 6.5 Keamanan

- AJAX memakai nonce **`lesson_editor_nonce`**.
- Hanya pengguna terautentikasi dengan kapabilitas yang sesuai yang dapat menjalankan otomasi penuh.

---

## 7. Pemecahan masalah (troubleshooting)

| Gejala | Periksa |
|--------|---------|
| Tombol AI tidak bereaksi | Pastikan halaman memuat shortcode `[my_lesson_editor]`; hard refresh; di DevTools → Network, pastikan `my-lesson-editor.js` termuat. |
| AJAX `admin-ajax.php` gagal | Login, peran `edit_posts`, nonce tidak kedaluwarsa (refresh halaman). |
| n8n HTTP bukan 2xx | URL webhook, workflow aktif, firewall host WordPress → n8n. |
| Preview kosong | Pastikan respons JSON memuat `title`/`content` atau `final_title`/`final_content`. |
| Author tidak muncul | CPT `author_profile` ada dan post *publish*. |
| Lesson tidak ter-load | ID lesson valid, tipe `lesson_profile`. |

---

## 8. Daftar shortcode cepat

| Shortcode | Plugin | Output |
|-----------|--------|--------|
| `[my_lesson_editor]` | My Lesson Editor | Dashboard lesson + AI + drafts. |
| `[my_book_editor]` | My Book Editor | Editor profil buku. |
| `[author_dashboard_v3]` / setara | My Author Editor (varian v3) | Dashboard author — sesuaikan dengan plugin yang terpasang. |

---

## 9. Changelog dokumen

| Versi | Tanggal | Perubahan |
|-------|---------|-----------|
| 1.0 | 2026 | Publikasi awal: alur Author → Book → Lesson, UI lesson, payload n8n, respons WP. |

---

## 10. Hak cipta dan dukungan

Dokumen ini mendampingi plugin **My Lesson Editor** dan ekosistem editor terkait. Untuk penyesuaian bisnis atau SLA dukungan, merujuk ke kebijakan internal organisasi Anda.

---

*Akhir dokumen — siap salin ke Google Docs (disarankan: Paste tanpa format lalu terapkan Heading 1/2/3 dan daftar bernomor sesuai struktur di atas).*
