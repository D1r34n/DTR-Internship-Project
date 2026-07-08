## Documentation

- [Coding Conventions](CODING_CONVENTIONS.md)
- DTR System Overview (see below)

### DTR System Overview

The Daily Time Record (DTR) System is a web-based application developed specifically for HSNP to streamline employee attendance, scheduling, and timekeeping. It enables employees to record their work hours while providing administrators with tools to manage attendance, schedules, employee records, and reporting through an intuitive dashboard.

#### Features

- Employee time-in and time-out recording with webcam capture
- Break-in and break-out recording
- Attendance history and daily time records
- Employee management
- User authentication and role-based access control
- Attendance reports and summaries
- Dashboard with attendance statistics
- Light mode and dark mode support
- Calendar view for employee work schedules
- Gantt-style attendance chart for visualizing employee attendance and work hours
- Responsive user interface for desktop and mobile devices

#### Technology Stack

- **Frontend:** React
- **Backend:** Node.js and Express
- **Database:** MySQL
- **Authentication:** JWT (JSON Web Token)
- **API:** RESTful API

#### Project Structure

```text
client/         Frontend React application
server/         Backend Express application
database/       Database schema and seed files
docs/           Project documentation
uploads/        Webcam images and other uploaded files
```

#### Core Modules

- Authentication and Authorization
- Employee Management
- Attendance Management
- Break Management
- Schedule Management
- Calendar
- Attendance Analytics and Reports
- Webcam Verification
- User Settings (Light/Dark Mode)