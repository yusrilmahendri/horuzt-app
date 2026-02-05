# Buku Tamu - UI Concept & Test Cases

Digital Wedding Guest Book UI specification for frontend implementation.

Version: 1.0.0
Last Updated: 2026-01-26


## Table of Contents

1. Public Guest View
2. User Dashboard View
3. Admin Dashboard View
4. Component Specifications
5. User Flows
6. Test Cases


## 1. Public Guest View

Guest-facing interface on wedding invitation page.


### 1.1 Guest Book Section Layout

```
+------------------------------------------------------------------+
|                                                                  |
|                    BUKU TAMU & UCAPAN                            |
|              Berikan ucapan dan doa untuk kami                   |
|                                                                  |
+------------------------------------------------------------------+

+------------------------------------------------------------------+
|  FORM SECTION                                                    |
+------------------------------------------------------------------+
|                                                                  |
|  Nama Lengkap *                                                  |
|  +------------------------------------------------------------+  |
|  | [input text]                                               |  |
|  +------------------------------------------------------------+  |
|                                                                  |
|  Email (Opsional)                                                |
|  +------------------------------------------------------------+  |
|  | [input email]                                              |  |
|  +------------------------------------------------------------+  |
|                                                                  |
|  Nomor Telepon (Opsional)                                        |
|  +------------------------------------------------------------+  |
|  | [input tel]                                                |  |
|  +------------------------------------------------------------+  |
|                                                                  |
|  Konfirmasi Kehadiran *                                          |
|  +------------------------------------------------------------+  |
|  | ( ) Hadir                                                  |  |
|  | ( ) Tidak Hadir                                            |  |
|  | ( ) Masih Ragu                                             |  |
|  +------------------------------------------------------------+  |
|                                                                  |
|  Jumlah Tamu (jika hadir)                                        |
|  +------------------------------------------------------------+  |
|  | [number input: 1-20]                                       |  |
|  +------------------------------------------------------------+  |
|                                                                  |
|  Ucapan & Doa *                                                  |
|  +------------------------------------------------------------+  |
|  | [textarea]                                                 |  |
|  |                                                            |  |
|  |                                                            |  |
|  +------------------------------------------------------------+  |
|  Min. 5 karakter                                    0/1000       |
|                                                                  |
|  +------------------------------------------------------------+  |
|  |              KIRIM UCAPAN                                  |  |
|  +------------------------------------------------------------+  |
|                                                                  |
+------------------------------------------------------------------+
```


### 1.2 Statistics Display

```
+------------------------------------------------------------------+
|  STATISTIK KEHADIRAN                                             |
+------------------------------------------------------------------+
|                                                                  |
|  +----------------+  +----------------+  +----------------+      |
|  |      87        |  |      23        |  |      15        |      |
|  |     Hadir      |  |  Tidak Hadir   |  |   Masih Ragu   |      |
|  |     (70%)      |  |     (18%)      |  |     (12%)      |      |
|  +----------------+  +----------------+  +----------------+      |
|                                                                  |
|  Total Tamu Hadir: 145 orang                                     |
|                                                                  |
+------------------------------------------------------------------+
```


### 1.3 Guest Book Entries List

```
+------------------------------------------------------------------+
|  UCAPAN DARI TAMU (125)                     [Filter: Semua v]    |
+------------------------------------------------------------------+
|                                                                  |
|  +------------------------------------------------------------+  |
|  |  [Avatar]  Budi Santoso                                    |  |
|  |            Hadir (2 orang)              2 jam yang lalu    |  |
|  |                                                            |  |
|  |  Barakallah untuk kalian berdua! Semoga menjadi keluarga   |  |
|  |  sakinah, mawaddah, warahmah. Aamiin.                      |  |
|  +------------------------------------------------------------+  |
|                                                                  |
|  +------------------------------------------------------------+  |
|  |  [Avatar]  Siti Nurhaliza                                  |  |
|  |            Tidak Hadir                   5 jam yang lalu   |  |
|  |                                                            |  |
|  |  Maaf tidak bisa hadir, tapi doa selalu menyertai kalian.  |  |
|  |  Happy wedding!                                            |  |
|  +------------------------------------------------------------+  |
|                                                                  |
|  +------------------------------------------------------------+  |
|  |  [Avatar]  Ahmad Wijaya                                    |  |
|  |            Masih Ragu                    1 hari yang lalu  |  |
|  |                                                            |  |
|  |  Selamat menempuh hidup baru!                              |  |
|  +------------------------------------------------------------+  |
|                                                                  |
|  +------------------------------------------------------------+  |
|  |              MUAT LEBIH BANYAK                             |  |
|  +------------------------------------------------------------+  |
|                                                                  |
+------------------------------------------------------------------+
```


### 1.4 Mobile Responsive Layout

```
+--------------------------------+
|                                |
|     BUKU TAMU & UCAPAN         |
|  Berikan ucapan untuk kami     |
|                                |
+--------------------------------+
|                                |
|  Nama Lengkap *                |
|  +---------------------------+ |
|  | [input]                   | |
|  +---------------------------+ |
|                                |
|  Email                         |
|  +---------------------------+ |
|  | [input]                   | |
|  +---------------------------+ |
|                                |
|  Telepon                       |
|  +---------------------------+ |
|  | [input]                   | |
|  +---------------------------+ |
|                                |
|  Konfirmasi Kehadiran *        |
|  +---------------------------+ |
|  | [select dropdown]         | |
|  +---------------------------+ |
|                                |
|  Jumlah Tamu                   |
|  +---------------------------+ |
|  | [number]                  | |
|  +---------------------------+ |
|                                |
|  Ucapan & Doa *                |
|  +---------------------------+ |
|  | [textarea]                | |
|  |                           | |
|  +---------------------------+ |
|                                |
|  +---------------------------+ |
|  |     KIRIM UCAPAN          | |
|  +---------------------------+ |
|                                |
+--------------------------------+
|  STATISTIK                     |
+--------------------------------+
|  Hadir: 87  | Tidak: 23        |
|  Ragu: 15   | Total Tamu: 145  |
+--------------------------------+
|  UCAPAN (125)       [Filter v] |
+--------------------------------+
|  +---------------------------+ |
|  | Budi Santoso              | |
|  | Hadir (2)    2 jam lalu   | |
|  | Barakallah untuk kalian..| |
|  +---------------------------+ |
|  +---------------------------+ |
|  | Siti Nurhaliza            | |
|  | Tidak Hadir  5 jam lalu   | |
|  | Maaf tidak bisa hadir...  | |
|  +---------------------------+ |
|                                |
|  [MUAT LEBIH BANYAK]           |
|                                |
+--------------------------------+
```


## 2. User Dashboard View

Wedding owner dashboard for managing guest book entries.


### 2.1 Main Dashboard Layout

```
+------------------------------------------------------------------+
|  SIDEBAR  |  BUKU TAMU                                           |
+-----------+------------------------------------------------------+
|           |                                                      |
|  Dashboard|  +--------------------------------------------------+|
|           |  | Statistik Kehadiran                              ||
|  Mempelai |  +--------------------------------------------------+|
|           |  |  +----------+ +----------+ +----------+ +-------+||
|  Acara    |  |  | 125      | | 87       | | 23       | | 15    |||
|           |  |  | Total    | | Hadir    | | Tidak    | | Ragu  |||
|  Galeri   |  |  | Ucapan   | | (70%)    | | (18%)    | | (12%) |||
|           |  |  +----------+ +----------+ +----------+ +-------+||
|  Cerita   |  |                                                  ||
|           |  |  Total Tamu Hadir: 145     Hari Ini: 12          ||
| >Buku Tamu|  +--------------------------------------------------+|
|           |                                                      |
|  Ucapan   |  +--------------------------------------------------+|
|           |  | Filter & Search                                  ||
|  Rekening |  +--------------------------------------------------+|
|           |  |                                                  ||
|  Setting  |  |  [Search nama/email...]  [Status: Semua v]       ||
|           |  |                                                  ||
|           |  |  [Approval: Semua v]      [Urutkan: Terbaru v]   ||
|           |  |                                                  ||
|           |  +--------------------------------------------------+|
|           |                                                      |
|           |  +--------------------------------------------------+|
|           |  | Bulk Actions                                     ||
|           |  +--------------------------------------------------+|
|           |  |                                                  ||
|           |  |  [x] Pilih Semua    Selected: 0                  ||
|           |  |                                                  ||
|           |  |  [Setujui] [Sembunyikan] [Hapus] [Export v]      ||
|           |  |                                                  ||
|           |  +--------------------------------------------------+|
|           |                                                      |
+-----------+------------------------------------------------------+
```


### 2.2 Data Table Layout

```
+------------------------------------------------------------------+
|  DATA BUKU TAMU                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  +----------------------------------------------------------------+
|  | [ ] | No | Nama        | Status    | Jumlah | Ucapan   | Aksi |
|  +----------------------------------------------------------------+
|  | [ ] | 1  | Budi S.     | Hadir     | 2      | Baraka...| [...] |
|  +----------------------------------------------------------------+
|  | [ ] | 2  | Siti N.     | Tidak     | -      | Maaf...  | [...] |
|  +----------------------------------------------------------------+
|  | [ ] | 3  | Ahmad W.    | Ragu      | 1      | Selama...| [...] |
|  +----------------------------------------------------------------+
|  | [ ] | 4  | Dewi P.     | Hadir     | 3      | Happy... | [...] |
|  +----------------------------------------------------------------+
|  | [ ] | 5  | Rudi H.     | Hadir     | 1      | Congrat..| [...] |
|  +----------------------------------------------------------------+
|                                                                  |
|  Showing 1-15 of 125              [<] [1] [2] [3] ... [9] [>]    |
|                                                                  |
+------------------------------------------------------------------+
```


### 2.3 Detail Modal

```
+------------------------------------------------------------------+
|  DETAIL UCAPAN                                              [X]  |
+------------------------------------------------------------------+
|                                                                  |
|  +------------------------------------------------------------+  |
|  |                                                            |  |
|  |  Nama        : Budi Santoso                                |  |
|  |  Email       : budi@example.com                            |  |
|  |  Telepon     : 08123456789                                 |  |
|  |  Status      : Hadir                                       |  |
|  |  Jumlah Tamu : 2 orang                                     |  |
|  |  Waktu       : 26 Jan 2026, 14:30 WIB                      |  |
|  |  Status      : Disetujui                                   |  |
|  |                                                            |  |
|  |  Ucapan:                                                   |  |
|  |  +--------------------------------------------------------+|  |
|  |  | Barakallah untuk kalian berdua! Semoga menjadi         ||  |
|  |  | keluarga sakinah, mawaddah, warahmah. Aamiin.          ||  |
|  |  +--------------------------------------------------------+|  |
|  |                                                            |  |
|  +------------------------------------------------------------+  |
|                                                                  |
|  +------------------------------------------------------------+  |
|  |  [Sembunyikan]    [Hapus]                         [Tutup]  |  |
|  +------------------------------------------------------------+  |
|                                                                  |
+------------------------------------------------------------------+
```


### 2.4 Export Modal

```
+------------------------------------------------------------------+
|  EXPORT DATA                                                [X]  |
+------------------------------------------------------------------+
|                                                                  |
|  Format Export:                                                  |
|  +------------------------------------------------------------+  |
|  | ( ) JSON                                                   |  |
|  | (o) CSV / Excel                                            |  |
|  +------------------------------------------------------------+  |
|                                                                  |
|  Data yang diexport:                                             |
|  +------------------------------------------------------------+  |
|  | (o) Semua Data (125)                                       |  |
|  | ( ) Data Terpilih (5)                                      |  |
|  | ( ) Filter Aktif (87 - Hadir)                              |  |
|  +------------------------------------------------------------+  |
|                                                                  |
|  Kolom:                                                          |
|  +------------------------------------------------------------+  |
|  | [x] Nama                                                   |  |
|  | [x] Email                                                  |  |
|  | [x] Telepon                                                |  |
|  | [x] Status Kehadiran                                       |  |
|  | [x] Jumlah Tamu                                            |  |
|  | [x] Ucapan                                                 |  |
|  | [x] Tanggal                                                |  |
|  +------------------------------------------------------------+  |
|                                                                  |
|  +------------------------------------------------------------+  |
|  |            [Batal]              [Download]                 |  |
|  +------------------------------------------------------------+  |
|                                                                  |
+------------------------------------------------------------------+
```


## 3. Admin Dashboard View

System administrator view for managing all guest book entries.


### 3.1 Admin Main Layout

```
+------------------------------------------------------------------+
|  ADMIN PANEL - BUKU TAMU                                         |
+------------------------------------------------------------------+
|                                                                  |
|  +--------------------------------------------------+            |
|  | Global Statistics                                |            |
|  +--------------------------------------------------+            |
|  |  +----------+ +----------+ +----------+ +-------+|            |
|  |  | 3,250    | | 2,145    | | 780      | | 325   ||            |
|  |  | Total    | | Hadir    | | Tidak    | | Ragu  ||            |
|  |  | Entries  | | (66%)    | | (24%)    | | (10%) ||            |
|  |  +----------+ +----------+ +----------+ +-------+|            |
|  |                                                  |            |
|  |  Users with Entries: 156    Today: 45            |            |
|  +--------------------------------------------------+            |
|                                                                  |
|  +--------------------------------------------------+            |
|  | Filter                                           |            |
|  +--------------------------------------------------+            |
|  |                                                  |            |
|  |  [Search...]  [User: Semua v]  [Status: Semua v] |            |
|  |                                                  |            |
|  |  [Approval: Semua v]  [Tanggal: Semua v]         |            |
|  |                                                  |            |
|  +--------------------------------------------------+            |
|                                                                  |
|  +--------------------------------------------------+            |
|  | Bulk Actions                                     |            |
|  +--------------------------------------------------+            |
|  |                                                  |            |
|  |  [x] Pilih Semua    Selected: 0                  |            |
|  |                                                  |            |
|  |  [Setujui] [Sembunyikan] [Hapus]                 |            |
|  |                                                  |            |
|  +--------------------------------------------------+            |
|                                                                  |
+------------------------------------------------------------------+
```


### 3.2 Admin Data Table

```
+------------------------------------------------------------------+
|  DATA BUKU TAMU (ALL USERS)                                      |
+------------------------------------------------------------------+
|                                                                  |
|  +---------------------------------------------------------------+
|  |[ ]|No|User        |Nama Tamu   |Status  |Jml|Ucapan   |Aksi  |
|  +---------------------------------------------------------------+
|  |[ ]|1 |Rudi & Ani  |Budi S.     |Hadir   |2  |Baraka...|[...] |
|  +---------------------------------------------------------------+
|  |[ ]|2 |Rudi & Ani  |Siti N.     |Tidak   |-  |Maaf...  |[...] |
|  +---------------------------------------------------------------+
|  |[ ]|3 |Doni & Lia  |Ahmad W.    |Ragu    |1  |Selama...|[...] |
|  +---------------------------------------------------------------+
|  |[ ]|4 |Budi & Dewi |Dewi P.     |Hadir   |3  |Happy... |[...] |
|  +---------------------------------------------------------------+
|                                                                  |
|  Showing 1-15 of 3,250           [<] [1] [2] [3] ... [217] [>]   |
|                                                                  |
+------------------------------------------------------------------+
```


## 4. Component Specifications


### 4.1 Form Components

Input Text (nama):
- Type: text
- Required: true
- Min Length: 2
- Max Length: 100
- Placeholder: "Masukkan nama lengkap"
- Validation Message: "Nama wajib diisi (min. 2 karakter)"

Input Email:
- Type: email
- Required: false
- Max Length: 100
- Placeholder: "email@example.com"
- Validation Message: "Format email tidak valid"

Input Telepon:
- Type: tel
- Required: false
- Max Length: 20
- Placeholder: "08xxxxxxxxxx"
- Pattern: numeric only

Radio Group (status_kehadiran):
- Type: radio
- Required: true
- Options:
  - value: "hadir", label: "Hadir"
  - value: "tidak_hadir", label: "Tidak Hadir"
  - value: "ragu", label: "Masih Ragu"
- Default: none
- Validation Message: "Pilih status kehadiran"

Input Number (jumlah_tamu):
- Type: number
- Required: false (required if status = hadir)
- Min: 1
- Max: 20
- Default: 1
- Show only when: status_kehadiran = "hadir"

Textarea (ucapan):
- Type: textarea
- Required: true
- Min Length: 5
- Max Length: 1000
- Rows: 4
- Placeholder: "Tulis ucapan dan doa untuk kedua mempelai..."
- Character Counter: show remaining characters
- Validation Message: "Ucapan minimal 5 karakter"

Submit Button:
- Type: submit
- Text: "KIRIM UCAPAN"
- Loading Text: "MENGIRIM..."
- Disabled: when form invalid or submitting


### 4.2 Card Components

Guest Entry Card:
- Avatar: generated from initials or default icon
- Name: bold, primary color
- Status Badge:
  - Hadir: green background
  - Tidak Hadir: red background
  - Ragu: yellow background
- Guest Count: show "(X orang)" if status = hadir
- Timestamp: relative time (X jam/hari yang lalu)
- Message: truncated to 150 chars with "..." if longer
- Click: expand to show full message

Statistics Card:
- Number: large, bold
- Label: below number, smaller text
- Percentage: show in parentheses
- Color:
  - Total: blue
  - Hadir: green
  - Tidak Hadir: red
  - Ragu: yellow


### 4.3 Table Components

Data Table:
- Columns:
  - Checkbox: for bulk selection
  - No: row number
  - Nama: guest name (truncated)
  - Status: badge
  - Jumlah: number or "-"
  - Ucapan: truncated text
  - Waktu: relative or formatted date
  - Aksi: dropdown menu
- Actions Menu:
  - Lihat Detail
  - Setujui / Sembunyikan (toggle)
  - Hapus

Pagination:
- Items per page: 10, 25, 50
- Show: "Showing X-Y of Z"
- Navigation: First, Prev, Page Numbers, Next, Last


### 4.4 State Indicators

Loading States:
- Form Submit: button spinner + "MENGIRIM..."
- Data Fetch: skeleton loader for cards/table
- Pagination: table overlay with spinner

Empty States:
- No Data: "Belum ada ucapan dari tamu"
- No Search Results: "Tidak ada data yang sesuai dengan pencarian"
- Icon: empty inbox illustration

Error States:
- Form Error: red border + message below input
- API Error: toast notification with retry option
- Network Error: "Koneksi terputus. Coba lagi."

Success States:
- Form Submit: toast "Ucapan berhasil dikirim!"
- Delete: toast "Data berhasil dihapus"
- Bulk Action: toast "X data berhasil diperbarui"


## 5. User Flows


### 5.1 Guest Submission Flow

```
START
  |
  v
[Open Invitation Page]
  |
  v
[Scroll to Buku Tamu Section]
  |
  v
[Fill Form]
  |
  +-- Invalid --> [Show Validation Errors] --> [Fill Form]
  |
  v
[Submit Form]
  |
  +-- Error --> [Show Error Toast] --> [Retry]
  |
  v
[Show Success Toast]
  |
  v
[Clear Form]
  |
  v
[Add Entry to List (top)]
  |
  v
[Update Statistics]
  |
  v
END
```


### 5.2 User Dashboard Flow

```
START
  |
  v
[Login]
  |
  v
[Navigate to Buku Tamu]
  |
  v
[Load Statistics + Data]
  |
  +-- [Search/Filter] --> [Update Data List]
  |
  +-- [View Detail] --> [Show Modal] --> [Close]
  |
  +-- [Toggle Approval] --> [Update Row] --> [Update Stats]
  |
  +-- [Delete Single] --> [Confirm] --> [Remove Row] --> [Update Stats]
  |
  +-- [Select Multiple] --> [Bulk Action] --> [Update Rows] --> [Update Stats]
  |
  +-- [Export] --> [Select Options] --> [Download File]
  |
  +-- [Pagination] --> [Load Page Data]
  |
  v
END
```


### 5.3 Admin Dashboard Flow

```
START
  |
  v
[Admin Login]
  |
  v
[Navigate to Buku Tamu]
  |
  v
[Load Global Statistics + Data]
  |
  +-- [Filter by User] --> [Update Data + Stats for User]
  |
  +-- [Filter by Status] --> [Update Data List]
  |
  +-- [View Detail] --> [Show Modal with IP] --> [Close]
  |
  +-- [Moderate Entry] --> [Approve/Hide] --> [Update Row]
  |
  +-- [Delete by User] --> [Confirm] --> [Remove All User Data]
  |
  +-- [Bulk Delete] --> [Confirm] --> [Remove Selected]
  |
  v
END
```


## 6. Test Cases


### 6.1 Public Form Tests

TC-PUB-001: Submit Valid Entry
- Precondition: Open invitation page with valid user_id
- Steps:
  1. Fill nama: "Test User"
  2. Fill email: "test@example.com"
  3. Fill telepon: "08123456789"
  4. Select status: "Hadir"
  5. Fill jumlah_tamu: 2
  6. Fill ucapan: "Selamat menempuh hidup baru!"
  7. Click submit
- Expected: 
  - API returns 201
  - Success toast appears
  - Form clears
  - New entry appears at top of list
  - Statistics update

TC-PUB-002: Submit Without Required Fields
- Steps:
  1. Leave nama empty
  2. Leave status_kehadiran unselected
  3. Leave ucapan empty
  4. Click submit
- Expected:
  - Form not submitted
  - Validation errors shown for nama, status_kehadiran, ucapan

TC-PUB-003: Submit With Invalid Email
- Steps:
  1. Fill nama: "Test User"
  2. Fill email: "invalid-email"
  3. Select status: "Hadir"
  4. Fill ucapan: "Test message"
  5. Click submit
- Expected:
  - Validation error: "Format email tidak valid"

TC-PUB-004: Submit With Short Ucapan
- Steps:
  1. Fill nama: "Test User"
  2. Select status: "Hadir"
  3. Fill ucapan: "Hi"
  4. Click submit
- Expected:
  - Validation error: "Ucapan minimal 5 karakter"

TC-PUB-005: Submit With Max Length Ucapan
- Steps:
  1. Fill all required fields
  2. Fill ucapan: 1000 characters
  3. Click submit
- Expected:
  - API returns 201
  - Entry saved successfully

TC-PUB-006: Character Counter
- Steps:
  1. Type in ucapan field
- Expected:
  - Counter shows "X/1000"
  - Counter turns red when approaching limit

TC-PUB-007: Jumlah Tamu Visibility
- Steps:
  1. Select status: "Tidak Hadir"
- Expected:
  - Jumlah tamu field hidden
- Steps:
  2. Select status: "Hadir"
- Expected:
  - Jumlah tamu field visible

TC-PUB-008: Load Guest Entries
- Precondition: Entries exist for user
- Steps:
  1. Open invitation page
- Expected:
  - Entries load with pagination
  - Statistics display correctly
  - Entries sorted by newest first

TC-PUB-009: Filter Entries by Status
- Steps:
  1. Click filter dropdown
  2. Select "Hadir"
- Expected:
  - Only "Hadir" entries shown
  - Count updates

TC-PUB-010: Load More Entries
- Precondition: More than 50 entries exist
- Steps:
  1. Scroll to bottom
  2. Click "Muat Lebih Banyak"
- Expected:
  - Next page loads
  - Appends to existing list


### 6.2 User Dashboard Tests

TC-USR-001: Load Dashboard Data
- Precondition: User logged in with entries
- Steps:
  1. Navigate to Buku Tamu menu
- Expected:
  - Statistics load correctly
  - Table shows user's entries only
  - Pagination works

TC-USR-002: Search by Name
- Steps:
  1. Type "Budi" in search field
  2. Press Enter or wait for debounce
- Expected:
  - Table filters to show only entries with "Budi" in nama

TC-USR-003: Filter by Status
- Steps:
  1. Select "Hadir" from status filter
- Expected:
  - Table shows only "Hadir" entries
  - Count reflects filtered data

TC-USR-004: View Entry Detail
- Steps:
  1. Click row or "Lihat Detail" action
- Expected:
  - Modal opens with full details
  - All fields displayed

TC-USR-005: Approve/Hide Entry
- Steps:
  1. Click "Sembunyikan" on approved entry
- Expected:
  - Entry is_approved changes to false
  - Row updates
  - Statistics update
- Steps:
  2. Click "Setujui" on hidden entry
- Expected:
  - Entry is_approved changes to true

TC-USR-006: Delete Single Entry
- Steps:
  1. Click "Hapus" on entry
  2. Confirm deletion
- Expected:
  - Entry removed from table
  - Statistics update
  - Success toast

TC-USR-007: Bulk Select Entries
- Steps:
  1. Check multiple checkboxes
- Expected:
  - Selected count updates
  - Bulk action buttons enabled

TC-USR-008: Bulk Approve
- Steps:
  1. Select 5 hidden entries
  2. Click "Setujui"
- Expected:
  - All 5 entries approved
  - Rows update
  - Toast: "5 data berhasil diperbarui"

TC-USR-009: Bulk Delete
- Steps:
  1. Select 3 entries
  2. Click "Hapus"
  3. Confirm deletion
- Expected:
  - 3 entries removed
  - Statistics update
  - Toast: "3 data berhasil dihapus"

TC-USR-010: Delete All Entries
- Steps:
  1. Click "Hapus Semua"
  2. Confirm deletion
- Expected:
  - All entries removed
  - Statistics reset to 0
  - Table shows empty state

TC-USR-011: Export to CSV
- Steps:
  1. Click "Export"
  2. Select CSV format
  3. Select all columns
  4. Click Download
- Expected:
  - CSV file downloads
  - File contains all entries
  - Columns match selection

TC-USR-012: Export to JSON
- Steps:
  1. Click "Export"
  2. Select JSON format
  3. Click Download
- Expected:
  - JSON response with all entries

TC-USR-013: Pagination Navigation
- Precondition: More than 15 entries
- Steps:
  1. Click page 2
- Expected:
  - Page 2 data loads
  - Pagination updates

TC-USR-014: Change Items Per Page
- Steps:
  1. Select "50" from items per page
- Expected:
  - Table shows 50 items
  - Pagination recalculates

TC-USR-015: Sort by Column
- Steps:
  1. Click "Nama" column header
- Expected:
  - Data sorts A-Z
- Steps:
  2. Click "Nama" again
- Expected:
  - Data sorts Z-A


### 6.3 Admin Dashboard Tests

TC-ADM-001: Load All Entries
- Precondition: Admin logged in
- Steps:
  1. Navigate to Admin Buku Tamu
- Expected:
  - Global statistics displayed
  - All user entries in table
  - User column shows wedding owner

TC-ADM-002: Filter by User
- Steps:
  1. Select specific user from filter
- Expected:
  - Table shows only that user's entries
  - Statistics update for that user

TC-ADM-003: View Entry with IP
- Steps:
  1. View entry detail
- Expected:
  - IP address displayed (admin only)

TC-ADM-004: Delete All by User
- Steps:
  1. Click "Hapus Semua" for specific user
  2. Confirm
- Expected:
  - All entries for that user deleted
  - Global statistics update

TC-ADM-005: Bulk Delete Across Users
- Steps:
  1. Select entries from different users
  2. Click bulk delete
  3. Confirm
- Expected:
  - All selected entries deleted
  - Statistics update


### 6.4 API Integration Tests

TC-API-001: GET Public List
- Request: GET /api/v1/buku-tamu?user_id=123
- Expected: 200 with data array and pagination

TC-API-002: GET Public List Missing user_id
- Request: GET /api/v1/buku-tamu
- Expected: 400 with error message

TC-API-003: POST Valid Entry
- Request: POST /api/v1/buku-tamu with valid body
- Expected: 201 with created entry

TC-API-004: POST Invalid Entry
- Request: POST /api/v1/buku-tamu with invalid body
- Expected: 422 with validation errors

TC-API-005: GET User List Authenticated
- Request: GET /api/v1/user/result-bukutamu with Bearer token
- Expected: 200 with user's entries only

TC-API-006: GET User List Unauthenticated
- Request: GET /api/v1/user/result-bukutamu without token
- Expected: 401 Unauthorized

TC-API-007: PATCH Approval Status
- Request: PATCH /api/v1/user/buku-tamu/1/approval
- Body: {"is_approved": false}
- Expected: 200 with updated entry

TC-API-008: DELETE Entry
- Request: DELETE /api/v1/user/buku-tamu/1
- Expected: 200 with success message

TC-API-009: DELETE Non-existent Entry
- Request: DELETE /api/v1/user/buku-tamu/99999
- Expected: 404 Not Found

TC-API-010: Admin GET All Entries
- Request: GET /api/v1/admin/buku-tamu with admin token
- Expected: 200 with all entries

TC-API-011: Admin GET Filtered by User
- Request: GET /api/v1/admin/buku-tamu?user_id=123
- Expected: 200 with filtered entries

TC-API-012: Admin Statistics
- Request: GET /api/v1/admin/buku-tamu/statistics
- Expected: 200 with global statistics

TC-API-013: Rate Limiting
- Request: 100 POST requests in 1 minute
- Expected: 429 Too Many Requests after limit


### 6.5 Edge Case Tests

TC-EDGE-001: XSS Prevention in Ucapan
- Steps:
  1. Submit ucapan with script tag
- Expected:
  - Script not executed
  - HTML escaped in display

TC-EDGE-002: SQL Injection Prevention
- Steps:
  1. Submit nama with SQL injection attempt
- Expected:
  - Data saved as literal string
  - No database error

TC-EDGE-003: Large Data Set Performance
- Precondition: 10,000+ entries
- Steps:
  1. Load user dashboard
- Expected:
  - Page loads within 3 seconds
  - Pagination works smoothly

TC-EDGE-004: Concurrent Submissions
- Steps:
  1. Two users submit simultaneously
- Expected:
  - Both entries saved
  - No data corruption

TC-EDGE-005: Network Timeout
- Steps:
  1. Simulate slow network
  2. Submit form
- Expected:
  - Loading indicator shows
  - Timeout error after 30 seconds
  - Retry option available

TC-EDGE-006: Session Expiry During Action
- Steps:
  1. User session expires
  2. Attempt to delete entry
- Expected:
  - 401 response
  - Redirect to login

TC-EDGE-007: Unicode Characters in Ucapan
- Steps:
  1. Submit ucapan with emoji and Arabic text
- Expected:
  - Characters saved and displayed correctly

TC-EDGE-008: Maximum jumlah_tamu Value
- Steps:
  1. Enter jumlah_tamu: 21
- Expected:
  - Validation error: max 20

TC-EDGE-009: Duplicate Submission Prevention
- Steps:
  1. Double-click submit button
- Expected:
  - Only one entry created
  - Button disabled during submission

TC-EDGE-010: Browser Back Button After Submit
- Steps:
  1. Submit form
  2. Click browser back
  3. Click forward
- Expected:
  - Form state preserved or cleared
  - No duplicate submission
