# Prompt Log AI – Tugas 3 Integrasi Aplikasi Enterprise

## Informasi Mahasiswa

* Nama : Dimas Chairul Anam
* Nama Service : Monitoring BBM Servis Service
* NIM : 102022400256
* Mata Kuliah : Integrasi Aplikasi Enterprise
* Tugas : Tugas 3 – Integrasi Federated SSO, SOAP XML Audit, dan RabbitMQ Publisher


## Prompt 1 – Implementasi Federated SSO

### Prompt
Bagaimana cara memperoleh token M2M dari Cloud Dosen menggunakan API Key dan menyesuaikan perubahan terbaru yang mewajibkan pengiriman NIM pada request body?

### Hasil
Berhasil memperoleh JWT Token M2M menggunakan API Key dan NIM sesuai format terbaru yang ditentukan dosen. Token digunakan sebagai Bearer Token untuk mengakses layanan SOAP Audit dan RabbitMQ Publisher.


## Integrasi SOAP XML Client
### Prompt
Bagaimana cara membuat SOAP XML Envelope menggunakan Laravel Http Client dan mengirimkan audit transaksi setelah proses penambahan data maintenance berhasil dilakukan?

### Hasil
Berhasil membuat SOAP XML Envelope sesuai format yang dibutuhkan dan mengirimkan Audit Request ke layanan SOAP Cloud Dosen menggunakan Laravel Http Client.


## Debugging SOAP Audit
### Prompt
Mengapa SOAP Audit menghasilkan status 400 dan bagaimana cara memperbaiki request XML yang dikirim agar sesuai dengan format server?

### Hasil
Berhasil mengidentifikasi kesalahan pada struktur XML dan field yang wajib dikirim, kemudian memperbaiki request sehingga SOAP Audit berhasil dijalankan dengan status **200 (SUCCESS)**.


## Integrasi RabbitMQ Publisher
### Prompt
Bagaimana cara mengirim event **maintenance.created** ke RabbitMQ menggunakan Laravel Http Client setelah data maintenance berhasil disimpan?

### Hasil
Berhasil mengirim event **maintenance.created** ke RabbitMQ Cloud Dosen menggunakan payload JSON sesuai spesifikasi sehingga event berhasil dipublikasikan.


## Pengujian Integrasi

### Prompt

Bagaimana cara memastikan proses POST `/api/v1/maintenance` berhasil melakukan penyimpanan data sekaligus menjalankan SOAP Audit dan RabbitMQ Publisher?

### Hasil

Berhasil melakukan pengujian endpoint menggunakan Postman. Hasil pengujian menunjukkan data maintenance berhasil disimpan ke database, SOAP Audit mengembalikan status **200**, dan RabbitMQ Publisher juga mengembalikan status **200**.


## Penyusunan Sequence Diagram

### Prompt

Buatkan sequence diagram yang menggambarkan alur transaksi kritis POST `/api/v1/maintenance` mulai dari pengguna, proses penyimpanan database, SOAP Audit, hingga RabbitMQ Publisher.

### Hasil

Berhasil menyusun sequence diagram yang menggambarkan alur transaksi kritis sesuai implementasi pada service Monitoring BBM Servis Service.


## Konfigurasi Docker

### Prompt

Bagaimana cara memperbaiki proses build Docker ketika Composer tidak ditemukan serta memastikan container Laravel dapat berjalan dengan benar?

### Hasil

Berhasil memperbaiki konfigurasi Docker sehingga proses build berhasil dijalankan, image berhasil dibuat, dan service Monitoring BBM Servis Service dapat dijalankan melalui Docker Compose.


## Kesimpulan

AI digunakan sebagai alat bantu untuk memahami requirement Tugas 3, membantu implementasi Federated SSO, integrasi SOAP XML Audit dan RabbitMQ Publisher, melakukan debugging terhadap proses integrasi, memperbaiki konfigurasi Docker, serta membantu penyusunan dokumentasi teknis. Seluruh implementasi, pengujian, validasi hasil, dan penyesuaian kode dilakukan secara mandiri pada layanan Monitoring BBM Servis Service.
