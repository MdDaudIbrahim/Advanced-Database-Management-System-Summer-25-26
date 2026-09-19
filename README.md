# Sports Tournament Management System (STMS)

**Course:** Advanced Database Management System (ADBMS) — Summer 2025–2026  
**Institution:** American International University-Bangladesh (AIUB)  

---

## 📌 Project Overview
The **Sports Tournament Management System (STMS)** is an enterprise-grade university sports management platform developed to streamline tournament planning, team and coach roster management, stadium venue allocation, match scheduling, ticket sales, and payment accounting.

The system is built on an **Oracle Database 10g Express Edition (XE)** backend normalized up to **3rd Normal Form (3NF)** with strict relational constraints, sequences, triggers, and automated multi-role access control.

---

## 🎯 Key Features
- **Unified Authentication & Automatic Role Detection:**
  - Zero manual role selector: Users enter their credentials, and the system automatically identifies their role (Admin, Coach, Staff, or Customer) by querying database records.
- **Administrator Portal:**
  - Live statistical dashboard (KPIs calculated via SQL aggregates).
  - Full CRUD operations for Tournaments, Teams, Venues, and Player Rosters.
  - Match scheduling with automated stadium conflict detection.
- **Coach Portal:**
  - Manage assigned team squad with validation rules (prevent duplicate jersey numbers within a squad).
  - View fixtures and register teams into open tournaments.
- **Data Entry Staff Portal:**
  - Compact, high-efficiency ticket payment management at stadium entrance gates.
  - Instant toggle between *Pending* and *Paid* statuses.
- **Customer (Spectator) Portal:**
  - Browse tournament schedules and upcoming fixtures.
  - Interactive stadium visualizer to book available seats without double-booking (`uq_match_seat` unique constraint).
  - Online ticket checkout with financial ledger integration.

---

## 🏛️ Database Architecture
- **DBMS:** Oracle Database 10g Express Edition (XE) / Fallback support
- **Schema Design:** 13 normalized relational tables:
  1. `TOURNAMENT`
  2. `COACH` & `COACH_PHONE` (1NF multivalued phone isolation)
  3. `TEAM`
  4. `PLAYER` & `PLAYER_PHONE`
  5. `REGISTRATION` (Many-to-Many bridge)
  6. `VENUE`
  7. `MATCHES`
  8. `SPECTATOR`
  9. `TICKET`
  10. `PAYMENT`
  11. `STAFF`
- **Automation:** 11 Oracle Sequences and Triggers for synthetic primary keys.
- **Integrity:** Foreign keys configured with `ON DELETE CASCADE` and check constraints.

---

## 🚀 Quick Setup & Installation

### 1. Prerequisites
- **XAMPP** (PHP 7.4+ or 8.x, Apache)
- **Oracle Database 10g Express Edition (XE)**

### 2. Deployment
1. Clone or extract this repository into your XAMPP web root:
   ```bash
   C:\xampp\htdocs\ALL CODES\ADMS STMS
   ```
2. Start the **Apache** server from the XAMPP Control Panel.

### 3. Oracle Database Setup
- **Automated 1-Click Import (Windows):**  
  Double-click `import_database.bat` in the project root directory. It will automatically connect to Oracle XE (`SCOTT/tiger@127.0.0.1:1521/XE`), execute `database/schema.sql`, and create all tables, sequences, triggers, and demo data.
- **Manual Import via SQL*Plus:**
  ```cmd
  cd "C:\xampp\htdocs\ALL CODES\ADMS STMS\database"
  sqlplus SCOTT/tiger@XE @schema.sql
  ```
- **Manual Import via Oracle APEX:**
  Log into `http://localhost:8080/apex`, go to **SQL Workshop** -> **SQL Scripts**, upload `database/schema.sql` and run.

### 4. Access the Application
Open your browser and navigate to:
```
http://localhost/ALL CODES/ADMS STMS/views/auth/login.php
```

### 🔑 Demo Accounts
| Role | Username / Email | Password |
| :--- | :--- | :--- |
| **Admin** | `admin` | `admin123` |
| **Coach** | `kamal.h@gmail.com` | `coach123` |
| **Staff** | `rahat.k@gmail.com` | `staff123` |
| **Customer** | `customer@example.com` | *any* |

---

## 👥 Contributors
Developed as part of the **Advanced Database Management System (ADBMS)** course curriculum for **Summer 2025–2026** at **American International University-Bangladesh (AIUB)**.
