# 🚀 PHP & MySQL Task Management Board (Trello/Jira-Lite)

A modern, high-end, and fully secure project and task management web application built from scratch using **HTML/CSS, native PHP (PDO), JavaScript, and MySQL**. Designed with a polished custom SaaS aesthetic, smooth micro-interactions, and a persistent dark/light theme toggle.

---

## ✨ Key Features

* **Custom SaaS UI & Design System:** Crafted with modern typography (Plus Jakarta Sans), subtle elevation shadows, smooth hover physics, and responsive layouts (inspired by Linear and modern Trello).
* **Interactive Dark/Light Theme Toggle:** Dynamic theme switching backed by browser `localStorage` to remember user preference instantly without page flickering.
* **Secure User Authentication:** Implements password hashing (`password_hash`), secure login verification (`password_verify`), session fixation defense (`session_regenerate_id`), and robust auth guards.
* **Relational Database Architecture:** Normalized SQL schema utilizing the InnoDB engine with foreign key constraints and `ON DELETE CASCADE` rules linking users, projects, and tasks.
* **Project Dashboard:** Create, view, and manage multiple projects tailored to the active user session.
* **Kanban Task Board:** Categorize tasks across three columns (**To Do**, **In Progress**, **Done**) complete with priority badges (Low, Medium, High), due dates, real-time status updates, and secure deletion controls.

---

## 🛠️ Tech Stack

* **Frontend:** HTML5, Modern CSS3 (CSS Variables, Flexbox, CSS Grid), Vanilla JavaScript (`localStorage`)
* **Backend:** PHP 8+ (PDO for secure database abstraction and prepared statements)
* **Database:** MySQL (Relational schema with InnoDB engine)
* **Environment:** Local MAMP / XAMPP server (Apache & MySQL)

---

## ⚙️ Local Installation & Setup

Follow these steps to set up and run the project locally on your machine:

1. **Clone the Repository:**
   ```bash
   git clone [https://github.com/YOUR_USERNAME/task-manager.git](https://github.com/YOUR_USERNAME/task-manager.git)