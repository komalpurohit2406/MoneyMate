
# MoneyMate – AI-Powered Personal Finance Management System

MoneyMate is a web-based personal finance management system that helps users track their income and expenses, monitor their financial balance, and receive AI-generated insights based on their recorded transactions.

## Features

- User registration and login
- Personal income tracking
- Expense recording and tracking
- Dashboard with financial summaries
- AI-powered financial insights using the Gemini API
- Individual user data separation
- Responsive web interface

## Technologies Used

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP
- **Database:** MySQL
- **AI Integration:** Google Gemini API
- **Local Development:** XAMPP
- **Deployment:** InfinityFree
- **Version Control:** Git and GitHub

## Live Demo

[Visit MoneyMate](https://moneymate-komal.infinityfree.io)

## Repository

[View the source code on GitHub](https://github.com/komalpurohit2406/MoneyMate)

## Local Setup

1. Install XAMPP and start Apache and MySQL.
2. Clone or download this repository into your XAMPP `htdocs` directory.
3. Create a MySQL database named `moneymate_db`.
4. Import `setup.sql` through phpMyAdmin.
5. Configure your local database connection using a private `db.php` file.
6. Configure the Gemini API key privately on your local environment.
7. Open `http://localhost/MoneyMate/` in your browser.

## Security Notes

- Never commit database passwords or API keys to GitHub.
- Keep private configuration files out of version control.
- Use the provided example configuration as a reference.
- Keep API credentials on the server rather than exposing them in frontend JavaScript.

## Project Purpose

The purpose of MoneyMate is to simplify personal finance tracking and provide useful, AI-generated insights that help users understand their recorded income and expenses.

## Disclaimer

AI-generated insights are informational and should not be considered professional financial advice.

---

**Developed as an academic project.**
