# 🎓 ONLINE STUDENT ADMISSION SYSTEM (OSAS)
## Kabarak University — DIT Project | Ruth Jebet | March 2026

---

## 📋 PROJECT OVERVIEW

A fully functional web-based Online Student Admission System built with:
- **PHP** (Server-side logic)
- **MySQL** (Database)
- **HTML & CSS** (Front-end)
- **JavaScript** (Client-side interactions)
- **XAMPP** (Local development server)

---

## 🚀 SETUP INSTRUCTIONS (How to Run)

### Step 1: Install XAMPP
Download and install XAMPP from https://www.apachefriends.org  
Start **Apache** and **MySQL** from the XAMPP Control Panel.

### Step 2: Copy Project Files
Copy the entire `osas` folder to:
```
C:\xampp\htdocs\osas\          (Windows)
/Applications/XAMPP/htdocs/osas/  (Mac)
/opt/lampp/htdocs/osas/           (Linux)
```

### Step 3: Set Up the Database
1. Open your browser and go to: http://localhost/phpmyadmin
2. Click **"Import"** in the top menu
3. Click **"Choose File"** and select `setup.sql` from the project folder
4. Click **"Go"** to run the script

OR run in the MySQL terminal:
```sql
source C:/xampp/htdocs/osas/setup.sql;
```

### Step 4: Open the System
Open your browser and go to:
```
http://localhost/osas/
```

---

## 🔑 DEFAULT LOGIN CREDENTIALS

| Role          | Email                 | Password   |
|---------------|-----------------------|------------|
| Administrator | admin@osas.ac.ke      | Admin@123  |
| Test Student  | student@test.com      | Test@123   |

---

## 📁 PROJECT FILE STRUCTURE

```
osas/
├── index.php           ← Login Page
├── register.php        ← Student Registration
├── apply.php           ← Application Form
├── status.php          ← Application Status (Student)
├── logout.php          ← Logout Handler
├── config.php          ← Database Configuration
├── setup.sql           ← Database Setup Script
├── css/
│   └── style.css       ← Shared Stylesheet
├── admin/
│   ├── dashboard.php   ← Admin Dashboard
│   └── view.php        ← View & Review Application
└── uploads/            ← Uploaded documents stored here
```

---

## ✨ KEY FEATURES

### Student Side
- ✅ Account registration with validation
- ✅ Secure login with password hashing
- ✅ Full application form (personal, academic, programme, guardian info)
- ✅ Document upload (KCSE certificate, ID, passport photo)
- ✅ Real-time application status tracking

### Admin Side
- ✅ Secure admin dashboard
- ✅ Summary statistics (total, pending, approved, rejected)
- ✅ Filter applications by status (Pending / Approved / Rejected)
- ✅ Search applications by name, programme, or email
- ✅ View full application details
- ✅ Approve or reject applications with comments

### Security Features
- ✅ Password hashing (bcrypt)
- ✅ SQL injection prevention (prepared statements)
- ✅ Session-based authentication
- ✅ Role-based access control (student vs admin)
- ✅ Input sanitization on all forms

---

## 🛠️ TECHNOLOGIES USED

| Technology     | Version     | Purpose                            |
|----------------|-------------|------------------------------------|
| PHP            | 8.x         | Server-side scripting              |
| MySQL          | 8.x         | Database management                |
| HTML5          | —           | Web page structure                 |
| CSS3           | —           | Styling and layout                 |
| JavaScript     | ES6+        | Client-side interactions           |
| XAMPP          | 8.x         | Local development environment      |

---

## 📞 CONTACT

**Student:** Ruth Jebet  
**Reg. No.:** DIT/N/3861/09/24  
**Institution:** Kabarak University  
**School:** School of Science, Engineering and Technology  
**Department:** Computer Science and Information Technology  
**Year:** 2026
