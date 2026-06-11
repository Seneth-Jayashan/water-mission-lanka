# Astro Starter Kit: Basics

```sh
npm create astro@latest -- --template basics
```

> 🧑‍🚀 **Seasoned astronaut?** Delete this file. Have fun!

## 🚀 Project Structure

Inside of your Astro project, you'll see the following folders and files:

```text
/
├── public/
│   └── favicon.svg
├── src
│   ├── assets
│   │   └── astro.svg
│   ├── components
│   │   └── Welcome.astro
│   ├── layouts
│   │   └── Layout.astro
│   └── pages
│       └── index.astro
└── package.json
```

To learn more about the folder structure of an Astro project, refer to [our guide on project structure](https://docs.astro.build/en/basics/project-structure/).

## 🧞 Commands

All commands are run from the root of the project, from a terminal:

| Command                   | Action                                           |
| :------------------------ | :----------------------------------------------- |
| `npm install`             | Installs dependencies                            |
| `npm run dev`             | Starts local dev server at `localhost:4321`      |
| `npm run build`           | Build your production site to `./dist/`          |
| `npm run preview`         | Preview your build locally, before deploying     |
| `npm run astro ...`       | Run CLI commands like `astro add`, `astro check` |
| `npm run astro -- --help` | Get help using the Astro CLI                     |

## 👀 Want to learn more?

Feel free to check [our documentation](https://docs.astro.build) or jump into our [Discord server](https://astro.build/chat).

## admin login URL LocalHost
http://localhost:4321/admin-login

## backend run command
php -S localhost:8000 -t backend

## 🗄️ Database Setup (Manual)

To manually create the database and set up the tables:

### 1. Create the Database
Log into your MySQL server (via CLI or a client like phpMyAdmin, DBeaver) and run:
```sql
CREATE DATABASE `water_mission` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

### 2. Import the Schema
Import the schema located in `backend/init.sql` using one of the following methods:

**Method A: Using MySQL Command Line (CLI)**
```sh
mysql -u root -p water_mission < backend/init.sql
```

**Method B: Using a MySQL client GUI (phpMyAdmin, DBeaver, etc.)**
1. Select the `water_mission` database.
2. Go to the **SQL** or **Query** tab.
3. Copy the entire contents of `backend/init.sql` and execute it.

## 🔄 Database Migrations

If you need to run schema updates (such as `backend/migrate_categories.sql` to add description and image columns to categories):

### Using XAMPP and VS Code Terminal

Open the VS Code Terminal (ensure your XAMPP MySQL server is running) and execute the command corresponding to your shell type:

**PowerShell (default in VS Code on Windows):**
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root water_mission < backend/migrate_categories.sql
```

**Git Bash / Command Prompt:**
```sh
C:\xampp\mysql\bin\mysql.exe -u root water_mission < backend/migrate_categories.sql
```

*(Note: If your XAMPP MySQL root user has a password, append `-p` to the end of the command and enter the password when prompted).*
```