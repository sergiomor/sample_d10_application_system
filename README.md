# Research Application Workflow Code Ontology

## 1. Module Architecture
```
research_application_workflow/
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
  - `research_application_workflow.form` → `ExpressionsInterest` (Form)
  - `research_application_workflow.expressions_interest` → `ActiveExpressionsInterest::dashboardIr`
  - `research_application_workflow.upload_expression_interest.form` → `UploadExpressionInterest` (Form)
  - `research_application_workflow.submit_application.form` → `SubmitApplication` (Form)

- **Admin Functions**
  - `research_application_workflow.application_settings.form` → `ApplicationSettings` (Form)
  - `research_application_workflow.download_csv` → `DownloadCandidatesCsv::downloadCsv`

- **Rankings System**
  - `research_application_workflow.ranking_per_field` → `RankingPerField`
  - `research_application_workflow.rankings_admin` → `RankingsEvaluatorsAdmin`

### Services
- `research_application_workflow.redirect_users` → Handles user redirects based on roles
- `research_application_workflow.rankings_manager` → Manages application rankings
- `research_application_workflow.taxonomy_print_configuration_subscriber` → Event subscriber for taxonomy printing

### Primary User Interfaces
1. **Candidate Portal** - For applicants to submit expressions of interest
2. **Researcher Portal** - For researchers to review expressions of interest
3. **Admin Portal** - For administrators to manage the application process
4. **Rankings Management** - For evaluating and ranking applications

## 3. Data Management

### Configuration Entities
- `research_application_workflow.applicationsettings` - Stores application settings like dates and email

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
