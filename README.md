# MediHub - Electronic Health Records System

A secure, consent-based Electronic Health Records (EHR) management system built with PHP and MySQL. This MVP prototype demonstrates core EHR flows including patient registration, record management, consent-based access control, and verifiable data deletion.

## Features

### Core Functionality
- **User Management**: Patient, Doctor, and Admin roles with appropriate permissions
- **EHR Records**: Create, view, edit, and delete medical records (encounters, lab results, prescriptions, notes, vitals, allergies, imaging)
- **Consent Management**: Granular consent system with scopes and time limits
- **File Uploads**: Secure document storage with SHA-256 verification
- **Audit Logging**: Tamper-evident hash-linked audit trail
- **Verifiable Deletion**: Complete data removal with cryptographic receipts
- **Admin Dashboard**: Anonymized analytics with k-anonymity protection

### Security Features
- Password hashing with PHP's `password_hash()`
- CSRF protection
- Rate limiting for login attempts
- Input validation and sanitization
- File type and size validation
- Session-based authentication

## Technology Stack

- **Backend**: PHP 8.2+ with PDO/MySQLi
- **Database**: MySQL 8.0+ or MariaDB 10.3+
- **Frontend**: HTML5, CSS3, Bootstrap 5, Vanilla JavaScript
- **Security**: PHP sessions, password hashing, CSRF tokens

## Installation

### Prerequisites
- PHP 8.2 or higher
- MySQL 8.0 or MariaDB 10.3 or higher
- Web server (Apache/Nginx)
- Composer (optional, for future enhancements)

### Setup Steps

1. **Clone or download the project**
   ```bash
   git clone <repository-url>
   cd mediHub-mvp
   ```

2. **Configure the database**
   - Create a MySQL database named `medihub`
   - Update database credentials in `config/db.php`:
   ```php
   $host = 'localhost';
   $dbname = 'medihub';
   $username = 'your_username';
   $password = 'your_password';
   ```

3. **Run database migrations**
   ```bash
   mysql -u your_username -p medihub < scripts/migrate.sql
   ```

4. **Seed the database with demo data**
   ```bash
   mysql -u your_username -p medihub < scripts/seed.sql
   ```

5. **Set up file permissions**
   ```bash
   chmod 755 uploads/
   chmod 644 .htaccess
   ```

6. **Configure web server**
   - Point document root to `mediHub-mvp/public/`
   - Ensure mod_rewrite is enabled (for Apache)
   - For Nginx, add rewrite rules for clean URLs

7. **Access the application**
   - Open your browser and navigate to `http://localhost/mediHub-mvp/public/`
   - Use the demo accounts to test the system

## Demo Accounts

After running the seed script, you can use these accounts:

### Admin
- **Email**: admin@medihub.com
- **Password**: password
- **Role**: System Administrator

### Doctors
- **Email**: dr.smith@medihub.com
- **Password**: password
- **Role**: Doctor (City General Hospital)

- **Email**: dr.jones@medihub.com
- **Password**: password
- **Role**: Doctor (Metro Medical Center)

### Patients
- **Email**: patient1@medihub.com
- **Password**: password
- **Role**: Patient (Alice Johnson)

- **Email**: patient2@medihub.com
- **Password**: password
- **Role**: Patient (Bob Wilson)

## API Endpoints

### Authentication
- `POST /?r=auth/register` - User registration
- `POST /?r=auth/login` - User login
- `POST /?r=auth/logout` - User logout
- `GET /?r=me` - Get current user info

### Patient-Doctor Links
- `POST /?r=links/request` - Request access to patient
- `POST /?r=links/approve` - Approve/reject access request
- `GET /?r=links/list&by=doctor|patient` - List links

### Consent Management
- `POST /?r=consents/create` - Create consent
- `POST /?r=consents/revoke` - Revoke consent
- `GET /?r=consents/list` - List consents

### EHR Records
- `POST /?r=ehr/create` - Create medical record
- `GET /?r=ehr/list` - List records
- `GET /?r=ehr/get&id=X` - Get specific record
- `POST /?r=ehr/update` - Update record
- `POST /?r=ehr/delete` - Delete record

### Document Management
- `POST /?r=doc/upload` - Upload document
- `GET /?r=doc/download&id=X` - Download document
- `POST /?r=doc/delete` - Delete document

### Admin Functions
- `GET /?r=admin/metrics` - Get anonymized metrics
- `GET /?r=admin/audit` - Get audit log

### Patient Functions
- `POST /?r=patient/deregister` - Request account deletion
- `GET /?r=patient/deletion-receipt` - Get deletion receipt

## Database Schema

The system uses the following main tables:
- `users` - User accounts and authentication
- `patients` - Patient-specific information
- `doctors` - Doctor-specific information
- `links` - Patient-doctor relationships
- `consents` - Consent agreements with scopes
- `consent_scopes` - Individual consent scopes
- `ehr_records` - Medical records (JSON content)
- `documents` - File attachments
- `audit_log` - Tamper-evident audit trail
- `deletion_jobs` - Account deletion tracking
- `notifications` - In-app notifications

## Security Considerations

### Data Protection
- All passwords are hashed using PHP's `password_hash()`
- Sensitive data is validated and sanitized
- File uploads are restricted by type and size
- CSRF tokens protect against cross-site request forgery

### Audit Trail
- All actions are logged with cryptographic hashes
- Hash chain ensures tamper detection
- Audit entries include actor, subject, action, and timestamp

### Consent Management
- Granular scope-based permissions
- Time-limited access with optional view limits
- Patients can revoke consent at any time

### Verifiable Deletion
- Complete data removal with cryptographic receipts
- Audit trail of deletion process
- Receipt includes hash verification

## Development

### Project Structure
```
mediHub-mvp/
├── public/                 # Web-accessible files
│   ├── index.php          # Main router
│   └── assets/            # CSS, JS, images
├── views/                 # HTML templates
├── controllers/           # Business logic
├── models/               # Database access
├── config/               # Configuration files
├── uploads/              # File storage
└── scripts/              # Database scripts
```

### Adding New Features
1. Create controller in `controllers/`
2. Add route to `public/index.php`
3. Create view in `views/`
4. Update database schema if needed
5. Add audit logging for new actions

## Production Deployment

### Security Checklist
- [ ] Change default passwords
- [ ] Use HTTPS in production
- [ ] Configure proper file permissions
- [ ] Set up database backups
- [ ] Enable PHP error logging
- [ ] Configure rate limiting
- [ ] Set up monitoring

### Performance Optimization
- Enable PHP OPcache
- Use database connection pooling
- Implement caching for frequently accessed data
- Optimize database queries
- Use CDN for static assets

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## Support

For support and questions, please open an issue in the repository or contact the development team.

## Changelog

### Version 1.0.0
- Initial MVP release
- Core EHR functionality
- Consent management system
- Audit logging
- Verifiable deletion
- Admin dashboard with k-anonymity
