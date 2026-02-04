# Laravel Booking Scheduler

This document describes the scheduled tasks configured in `routes/console.php` using **Laravel Scheduler**.

These scheduled tasks automatically update booking statuses based on the **current date** and **booking state**, following business rules.

Laravel documentation reference:  
https://laravel.com/docs/11.x/scheduling#scheduling-artisan-commands

---

## Overview

The application uses **Laravel's task scheduling** feature to automate booking status updates.  
A single system cron job triggers Laravel’s scheduler, which then runs the defined tasks at specific times.

All scheduled tasks are defined in:

routes/console.php


---

## Scheduled Tasks

### 1️⃣ Complete Checked-Out Bookings (10:00 AM)

**Schedule:**  
- Runs daily at **10:00 AM**

**Logic:**  
- Checks the current date
- Finds bookings where:
  - `check_out = today`
  - `status = occupied`
- Updates status to:
  - `complete`

**Purpose:**  
Automatically marks bookings as completed after guests have checked out.

---

### 2️⃣ Occupy Checked-In Bookings (12:00 PM)

**Schedule:**  
- Runs daily at **12:00 PM**

**Logic:**  
- Checks the current date
- Finds bookings where:
  - `check_in = today`
  - `status = paid`
- Updates status to:
  - `occupied`

**Purpose:**  
Automatically updates paid bookings to occupied on the check-in date.

---

### 3️⃣ Cancel Pending Bookings (12:00 PM)

**Schedule:**  
- Runs daily at **12:00 PM**

**Logic:**  
- Checks the current date
- Finds bookings where:
  - `check_in = today`
  - `status = pending`
- Updates status to:
  - `cancelled`

**Purpose:**  
Automatically cancels bookings that were not confirmed (paid) by the check-in date.

---

## Scheduler Configuration

Laravel’s scheduler requires **one cron entry** on the server.

### System Cron Job (Production)

Add the following to the server’s crontab:

```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1

⚠️ Only one cron job is required, regardless of the number of scheduled tasks.


Testing the Scheduler
View Registered Scheduled Tasks
php artisan schedule:list

Run Scheduled Tasks Manually
php artisan schedule:run

These commands are useful for local development and debugging.

