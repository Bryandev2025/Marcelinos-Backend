Centralized Database + S3 File Upload

## Overview

The Method uses **Cpanel Database(phpmyadmin) + S3** feature to centralized our team database and File Upload.  


All Centralized Database and S3 Configurations are defined in:

.env

---

## Step by Step Guide

### 1️⃣ Login to Cpanel

**Username:**  
dfoiwidm

**Password:**  
8xDT2fy;4LM4b(

**Purpose:**  
To put your IPv4 Address in the Remote Database Access 

---

### 2️⃣ Search for "Remote Database Access" in the Search Box or Scroll Down to the "Database" Section

**Find your Ip Address:**  
- Goto Google Chrome Search For "Whats my ip address?"
- Find "https://whatismyipaddress.com/"
- Wait for it to load your IPv4 and Copy it
IPv4 Examples:
49.145.164.95
131.226.110.203


**Remote Database Access:**  
- Add your IPv4 Address tp the Input Field "IP or Hostname"
- Comment is Optional
- Press Add Host
 

**Purpose:**  
To allow your Local Computer to Access the Database in the Cpanel

---

### 3️⃣ Copy the .env below and paste it in your .env file

**.env:**  
- Open Slack and find the message i sent.
- Copy the .env code and paste it in your .env file


### Done

