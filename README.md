# Custom Module Code Ontology

## 1. Module Architecture
```
custom_module/
├── config/
│   ├── schema/          # Configuration schema definitions
│   └── optional/        # Optional configuration
├── src/
│   ├── Controller/      # Page controllers
│   ├── Form/            # Form definitions
│   ├── EventSubscriber/ # Event listeners
│   ├── Plugin/          # Drupal plugins
│   └── Service/         # Custom services
└── module files         # .yml configuration files
```

## 2. Core Components

### Routes & Controllers
- **Applications Management**
  - `custom_module.form` → `ExpressionsInterest` (Form)
  - `custom_module.expressions_interest` → `ActiveExpressionsInterest::dashboardIr`
  - `custom_module.upload_expression_interest.form` → `UploadExpressionInterest` (Form)
  - `custom_module.submit_application.form` → `SubmitApplication` (Form)

- **Admin Functions**
  - `custom_module.application_settings.form` → `ApplicationSettings` (Form)
  - `custom_module.download_csv` → `DownloadCandidatesCsv::downloadCsv`

- **Rankings System**
  - `custom_module.ranking_per_field` → `RankingPerField`
  - `custom_module.rankings_admin` → `RankingsEvaluatorsAdmin`

### Services
- `custom_module.redirect_users` → Handles user redirects based on roles
- `custom_module.rankings_manager` → Manages application rankings
- `custom_module.taxonomy_print_configuration_subscriber` → Event subscriber for taxonomy printing

### Primary User Interfaces
1. **Candidate Portal** - For applicants to submit expressions of interest
2. **Researcher Portal** - For researchers to review expressions of interest
3. **Admin Portal** - For administrators to manage the application process
4. **Rankings Management** - For evaluating and ranking applications

## 3. Data Management

### Configuration Entities
- `custom_module.applicationsettings` - Stores application settings like dates and email

### Business Logic
- Application submission workflow
- Approval processes
- Ranking and evaluation system

## 4. User Roles & Permissions
- `candidato` - Regular applicants
- `candidato_enviado` - Applicants who have submitted
- `investigador_responsable` - Researchers reviewing applications
- `administrator` - System administrators

This modular architecture follows Drupal's component-based design pattern, with clear separation between:
- Routes (defining URLs)
- Controllers (handling requests)
- Forms (user interaction)
- Services (business logic)

The module manages a complete application workflow system for research positions, from submission to evaluation and ranking.
