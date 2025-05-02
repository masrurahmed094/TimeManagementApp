Smart Time Manager
This repository contains the code for the Smart Time Manager, a web-based application designed to help users manage their personal tasks and collaborate within teams.

Table of Contents
Features
Getting Started
Prerequisites
Installation
  
Database Structure
File Descriptions
Usage
Contributing
License
Features
The Smart Time Manager allows users to:

Register and log in to the application.
View a dashboard with a summary of their tasks, progress towards completion, and upcoming reminders.
Manage their personal tasks, including adding, updating, and deleting tasks.
Create and manage teams.
Invite and remove members from teams (Admin functionality).
Assign tasks to team members (Admin functionality).
Leave teams.
Getting Started
Prerequisites
Before you begin, ensure you have the following installed:

Web server with PHP support (e.g., Apache, Nginx)
MySQL or MariaDB database server
PHPMyAdmin (optional, for database management)
Installation
Clone the repository to your web server's root directory.
Import the provided SQL file (smart_time_manager.sql) into your database server to create the necessary tables and populate them with initial data.
Update the database connection details in db.php (this file was not provided, so you will need to create it with your database credentials).
Ensure your web server is configured to serve the PHP files.
Access the application through your web browser.
Database Structure
The database smart_time_manager contains the following tables:

tasks: Stores individual and team tasks.
id (INT, Primary Key, Auto-increment)
user_id (INT, Foreign Key to users table, ON DELETE CASCADE)
team_id (INT, Foreign Key to teams table, ON DELETE SET NULL)
title (VARCHAR(255))
description (TEXT, Nullable)
status (ENUM('pending', 'completed'), Default 'pending')
created_at (TIMESTAMP, Default CURRENT_TIMESTAMP)
due_date (DATE, Nullable)
priority (ENUM('High', 'Medium', 'Low'), Default 'Medium')
scheduled_date (DATETIME, Nullable)
assigned_to (INT, Foreign Key to users table, ON DELETE SET NULL, Nullable)
teams: Stores team information.
id (INT, Primary Key, Auto-increment)
name (VARCHAR(255), Unique)
description (TEXT, Nullable)
creator_id (INT, Foreign Key to users table, ON DELETE SET NULL, Nullable)
created_at (TIMESTAMP, Default CURRENT_TIMESTAMP)
team_members: Links users to teams and defines their role.
team_id (INT, Primary Key, Foreign Key to teams table, ON DELETE CASCADE)
user_id (INT, Primary Key, Foreign Key to users table, ON DELETE CASCADE)   
role (ENUM('admin', 'member'), Default 'member')
joined_at (TIMESTAMP, Default CURRENT_TIMESTAMP)
users: Stores user information.
id (INT, Primary Key, Auto-increment)
name (VARCHAR(255))
email (VARCHAR(255), Unique)
password (VARCHAR(255))
created_at (TIMESTAMP, Default CURRENT_TIMESTAMP)
The SQL dump includes sample data for the tasks and users tables.

File Descriptions
create_team.php: Handles the creation of new teams.
db.php: (Not provided) Contains the database connection logic. You will need to create this file.
delete_team.php: Handles the deletion of teams by the creator/admin.
index.php: The user dashboard, displaying task summaries, progress, reminders, and a calendar view. Includes functionality to schedule tasks.
leave_team.php: Allows a user to leave a team. Includes logic to transfer admin rights if the leaving user is an admin and to delete the team if it becomes empty.
login.php: Handles user login.
logout.php: Handles user logout.
manage_tasks.php: Provides an interface for users to add, update, and delete their personal tasks. Includes filtering and sorting options.
manage_team.php: Provides an interface for managing a specific team, including viewing members, inviting new members, assigning tasks, and removing tasks/members (Admin functionality).
my_teams.php: Displays a list of teams the user is a member of, with options to manage or leave teams.
register.php: Handles new user registration.
schedule_tasks.php: Handles the scheduling of tasks, updating the scheduled_date in the database.
smart_time_manager.sql: (Provided in the prompt) The SQL file containing the database schema and sample data.
styles.css: (Not provided) Contains the styling for the application.
Usage
Register a new account or log in with existing credentials.
Navigate to the dashboard to see your task summary and upcoming tasks.   
Use the "Manage Tasks" page to add, edit, or delete your personal tasks.
Go to "My Teams" to view teams you belong to or create a new one.
If you are an admin of a team, use the "Manage" option to invite members and assign team tasks.
Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.
