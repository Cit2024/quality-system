# Quality System - Complete Architecture & Data Flow

## Database Architecture

### citcoder_Quality Database (Form Management)

```
┌─────────────────────────────────────────────────────────────┐
│                   citcoder_Quality Database                 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────┐         ┌──────────────────────────┐      │
│  │    Admin     │ creates │        Form              │      │
│  ├──────────────┤─────────├──────────────────────────┤      │
│  │ ID (PK)      │         │ ID (PK)                  │      │
│  │ username     │         │ Title                    │      │
│  │ password     │         │ Description              │      │
│  │ Permissions: │         │ FormStatus (draft/pub)   │      │
│  │ - isCanCreate│         │ FormType                 │      │
│  │ - isCanDelete│         │ FormTarget               │      │
│  │ - isCanUpdate│         │ note                     │      │
│  │ - isCanRead  │         │ created_by (FK→Admin)    │      │
│  │ - isCanGet.. │         │ created_at               │      │
│  └──────────────┘         └──────┬───────────────────┘      │
│                                   │ has                     │
│                                   ▼                         │
│                          ┌──────────────────┐               │ 
│                          │    Section       │               │
│                          ├──────────────────┤               │
│                          │ ID (PK)          │               │
│                          │ IDForm (FK→Form) │               │
│                          │ title            │               │
│                          └────────┬─────────┘               │
│                                   │ has                     │
│                                   ▼                         │
│                          ┌──────────────────────────┐       │
│                          │      Question            │       │ 
│                          ├──────────────────────────┤       │
│                          │ ID (PK)                  │       │
│                          │ IDSection (FK→Section)   │       │
│                          │ TypeQuestion:            │       │
│                          │  - multiple_choice       │       │
│                          │  - true_false            │       │
│                          │  - essay                 │       │
│                          │  - evaluation            │       │
│                          │ TitleQuestion            │       │
│                          │ Choices                  │       │
│                          └──────────────────────────┘       │
└─────────────────────────────────────────────────────────────┘
``` 
### citcoder_Citgate Database (Student/Teacher Info)

```
┌──────────────────────────────────────────────────────────────┐
│                 citcoder_Citgate Database                    │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─────────────┐    ┌─────────────┐    ┌──────────────┐      │
│  │ sprofiles   │    │ regteacher  │    │    zaman     │      │
│  ├─────────────┤    ├─────────────┤    ├──────────────┤      │
│  │ KidNo (PK)  │    │ id (PK)     │    │ ZamanNo (PK) │      │
│  │ KesmNo      │    │ name        │    │ ZamanName    │      │
│  │ (Student)   │    │ (Teacher)   │    │ (Semester)   │      │
│  └─────────────┘    └─────────────┘    └──────────────┘      │
│                                                              │
│  ┌──────────────┐    ┌────────────────────────────┐          │
│  │    mawad     │    │    coursesgroups           │          │
│  ├──────────────┤    ├────────────────────────────┤          │
│  │ MadaNo (PK)  │    │ ZamanNo (FK)               │          │
│  │ MadaName     │    │ MadaNo (FK)                │          │
│  │ (Course)     │    │ GNo                        │          │
│  └──────────────┘    │ TNo (FK→regteacher)        │          │
│                      │ (Course-Teacher Assign)    │          │
│  ┌──────────────┐    └────────────────────────────┘          │
│  │  divitions   │                                            │
│  ├──────────────┤                                            │
│  │ did (PK)     │                                            │
│  │ KesmNo       │                                            │
│  │ Depname      │                                            │
│  │ dname        │                                            │
│  │ (Departments)│                                            │
│  └──────────────┘                                            │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

## User Roles & Authentication

```
┌─────────────────────────────────────────────────────────────┐
│                  User Roles & Authentication                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐    │
│   │  Admin   │  │ Student  │  │ Teacher  │  │  Alumni  │    │
│   ├──────────┤  ├──────────┤  ├──────────┤  ├──────────┤    │
│   │ Login:   │  │ Access:  │  │ Login:   │  │ Access:  │    │
│   │ login.   │  │ Direct   │  │ login.   │  │ form-    │    │
│   │ php      │  │ Link or  │  │ php      │  │ login.   │    │
│   │ (user/   │  │ form-    │  │ (ID/     │  │ php      │    │
│   │ pwd)     │  │ login)   │  │ auto-pwd)│  │ (grad    │    │
│   └──────────┘  └──────────┘  └──────────┘  │ info)    │    │
│                                             └──────────┘    │
│   ┌──────────┐                                              │
│   │Employer  │                                              │
│   ├──────────┤                                              │
│   │ Access:  │     Form Types:                              │
│   │ Direct   │     • course_evaluation                      │
│   │ Link     │     • teacher_evaluation                     │
│   └──────────┘     • program_evaluation                     │
│                    • facility_evaluation                    │
└─────────────────────────────────────────────────────────────┘
```

## Type Management System (Added 2025-11-29, Refactored 2025-11-29)

```
┌───────────────────────────────────────────────────────────────┐
│                   Type Management Architecture                │
├───────────────────────────────────────────────────────────────┤
│                                                               │
│  Form Edit Page (Self-Contained)                              │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │ • forms/edit-form.php                                   │  │
│  │                                                         │  │
│  │   Manages (via internal POST handlers):                 │  │
│  │   - FormTypes table (Name, Slug, Icon, Allowed Targets) │  │
│  │   - EvaluatorTypes table (Name, Slug, Icon)             │  │
│  │   - Form Password (form.password)                       │  │
│  │   - FormAccessFields (registration fields)              │  │
│  │                                                         │  │
│  │   Actions:                                              │  │
│  │   - action=create_type    → Insert into FormTypes/      │  │
│  │                              EvaluatorTypes             │  │
│  │   - action=delete_type    → Delete from FormTypes/      │  │
│  │                              EvaluatorTypes             │  │
│  │   - action=update_password → Update Form.password       │  │
│  │   - action=add_field      → Insert FormAccessFields     │  │
│  │   - action=delete_field   → Delete FormAccessFields     │  │
│  └─────────────────────────────────────────────────────────┘  │
│                                                               │
│  Database Tables                                              │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ FormTypes            EvaluatorTypes                      │ │
│  │ ├─ ID (PK)           ├─ ID (PK)                          │ │
│  │ ├─ Name              ├─ Name                             │ │
│  │ ├─ Slug              ├─ Slug                             │ │
│  │ └─ Icon              └─ Icon                             │ │
│  │                                                          │ │
│  │ FormType_EvaluatorType (Many-to-Many)                    │ │
│  │ ├─ FormTypeID (FK→FormTypes)                             │ │
│  │ └─ EvaluatorTypeID (FK→EvaluatorTypes)                   │ │
│  │ FormType_EvaluatorType (Many-to-Many)                    │ │
│  │ ├─ FormTypeID (FK→FormTypes)                             │ │
│  │ └─ EvaluatorTypeID (FK→EvaluatorTypes)                   │ │
│  │                                                          │ │
│  │ FormAccessFields                                         │ │
│  │ ├─ ID (PK), FormID (FK)                                  │ │
│  │ ├─ Label (Arabic Name)                                   │ │
│  │ ├─ Slug (URL Param)                                      │ │
│  │ └─ FieldType, IsRequired                                 │ │
│  └──────────────────────────────────────────────────────────┘ │
│                                                               │
│  Features:                                                    │
│  • Create new form types and evaluator types via admin UI     │
│  • Edit existing types (name, slug, icon)                     │
│  • Delete types (with usage validation)                       │
│  • Manage allowed targets (which evaluators can use which     │
│    form types)                                                │
│  • Database-driven constants (no code changes needed)         │
│  • Set password protection per form                           │
│  • Define custom registration fields per form                 │
│  • All logic self-contained in edit-form.php (no admin/api)   │
│                                                               │
└───────────────────────────────────────────────────────────────┘
```

## Unified Response Handler (Added 2025-11-29)

```
┌────────────────────────────────────────────────────────────────┐
│                   Unified Response Architecture                 │
│                                                                │
│  1. Entry Point: evaluation/submit.php                         │
│     - Single POST target for ALL forms                         │
│     - Instantiates ResponseHandler                             │
│                                                                │
│  2. Core Logic: helpers/ResponseHandler.php                    │
│     - validateAccessFields(): Checks FormAccessFields          │
│     - enrichStudentMetadata(): Auto-finds Teacher ID           │
│     - saveAnswers(): Saves to EvaluationResponses              │
│                                                                │
│  3. Dynamic Metadata                                           │
│     - No hardcoded columns (except standard ones)              │
│     - All registration fields saved in JSON `Metadata`         │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```
```

## Application Components

```
┌────────────────────────────────────────────────────────────────┐
│                    Application Components                      │
├────────────────────────────────────────────────────────────────┤
│  │   form-teacher.php  │  │  ┌──────────────────────┐          │
│  │   form-alumni.php   │  │  │ • config/            │          │
│  │   form-admin.php    │  │  │   DbConnection.php   │          │
│  │   form-employer.php │  │  │   dbConnectionCit    │          │
│  └─────────────────────┘  │  │ • helpers/           │          │
│                           │  │   database.php       │          │
│  Statistics & Analytics   │  │   units.php          │          │
│  ┌─────────────────────┐  │  │ • forms/             │          │
│  │ • statistics.php    │  │  │   form_constants.php │          │
│  │ • statistics/       │  │  └──────────────────────┘          │
│  │   get_statistics    │  │                                    │
│  │   get_teacher_stats │  │  UI & Scripts                      │
│  │   router.php        │  │  ┌──────────────────────┐          │
│  │   analytics/        │  │  │ • styles/            │          │
│  │   PDFReportGen.js   │  │  │   global.css         │          │
│  │ • scripts/          │  │  │   dashboard.css      │          │
│  │   statistics.js     │  │  │   forms.css          │          │
│  └─────────────────────┘  │  │ • scripts/           │          │
│                           │  │   main.js            │          │
│  Shared Components        │  │   dashbord.js        │          │
│  ┌─────────────────────┐  │  │   evaluation-form.js │          │
│  │ • components/       │  │  └──────────────────────┘          │
│  │   header.php        │  │                                    │
│  │   footer.php        │  │                                    │
│  │   navigation.php    │  │                                    │
│  │   separator.html    │  │                                    │
│  └─────────────────────┘  │                                    │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

## Data Flow & System Operations

### Complete Flow (10 Steps)

```
1. Form Creation                    2. Form Publishing
┌──────────────────────┐           ┌──────────────────────┐
│ Admin creates form   │──────────▶│ Admin sets status    │
│ with sections and    │           │ to 'published' and   │
│ questions via        │           │ generates evaluation │
│ forms/create-form    │           │ link                 │
└──────────────────────┘           └──────────┬───────────┘
                                              │
3. Link Distribution                          │
┌──────────────────────┐                      │
│ Evaluation link      │◀─────────────────────┘
│ shared with target   │
│ audience (students,  │
│ teachers, etc.)      │
└──────────┬───────────┘
           │
           ▼
4. Authentication                   5. Form Loading
┌──────────────────────┐           ┌──────────────────────┐
│ Evaluator accesses:  │──────────▶│ evaluation-form.php  │
│ - Direct link        │           │ checks if form       │
│ If form requires auth│           │ requires password or │
│ (password/fields):   │           │ registration fields  │
│ → Redirect to        │           │ If yes and not auth: │
│   login-form.php     │           │ → Redirect to        │
│ - Validates password │           │   login-form.php     │
│ - Collects fields    │           │ Else: renders form   │
└──────────────────────┘           └──────────┬───────────┘
                                              │
                                              ▼
6. Answer Collection                7. Form Submission
┌──────────────────────┐           ┌──────────────────────┐
│ Evaluator fills form │──────────▶│ Client validation,   │
│ with various         │           │ then POST to handler │
│ question types       │           │ (evaluation/         │
└──────────────────────┘           │  submit.php)         │
                                   └──────────┬───────────┘
                                              │
                                              ▼
8. Data Storage                     9. Unified Processing
┌──────────────────────┐           ┌──────────────────────┐
│ Responses saved to   │◀──────────│ helpers/             │
│ EvaluationResponses  │           │ ResponseHandler.php  │
│ with metadata (JSON) │           │ - Validates fields   │
└──────────────────────┘           │ - Lookups (Teacher)  │
                                   │ - Saves Answers      │
                                   └──────────────────────┘
                                              │
                                              ▼
                                   10. Dashboard Display
                                   ┌──────────────────────┐
                                   │ dashboard.php and    │
                                   │ statistics.php show  │
                                   │ visual analytics     │
                                   └──────────────────────┘
```

## Key Technical Details

### Form Link Format
```
evaluation-form.php?evaluation={FormType}&Evaluator={FormTarget}
                   &IDStudent={ID}&IDCourse={ID}
                   &Semester={ID}&IDGroup={ID}
```

### Session Data Priority
- Session data prioritized over URL params for security
- Populated from `form-login.php`

### Answer Storage (JSON)
```json
{
  "value": "answer_content",
  "type": "question_type"
}
```

### Metadata Storage (JSON)
```json
{
  "student_id": "123",
  "course_id": "CS101",
  "teacher_id": "T456"
}
```
- Virtual columns auto-extracted for indexing

### Security Measures
- **XSS Protection**: `htmlspecialchars()` on all output
- **SQL Injection**: Prepared statements throughout
- **Authentication**: Session-based for evaluators
- **Password Storage**: Plain text (should use `password_hash()` for production)
- **Form Access Control**: Optional password and registration fields per form

### Statistics Queries
- Use prepared statements
- Filtered by: `FormType`, `FormTarget`, `Semester`
- Aggregated using SQL GROUP BY

### PDF Reports
- Generated client-side with `pdfmake.js`
- Arabic text support with word reversal
- Decimal rounding to 2 places

### Authentication Flow Summary
```
Admin/Teacher  ──▶  login.php (username/password or ID/auto-pwd)
                            │
                            ▼
                      Session created
                            │
                            ▼
                      Dashboard access

Students       ──▶  Direct link OR form-login.php
                            │
                            ▼
                    Session with parameters
                            │
                            ▼
                    evaluation-form.php

Alumni         ──▶  form-login.php (graduation info)
                            │
                            ▼
                    Session with alumni data
                            │
                            ▼
                    evaluation-form.php

Employer       ──▶  Direct link
                            │
                            ▼
                    evaluation-form.php
```

## System Features

### Form Types
1. **course_evaluation** - Students evaluate courses
2. **teacher_evaluation** - Students evaluate teachers
3. **program_evaluation** - Alumni evaluate programs
4. **facility_evaluation** - Various evaluators assess facilities

### Question Types
1. **multiple_choice** - Single/multi-select options
2. **evaluation** - Likert scale (1-5 rating)
3. **true_false** - Boolean questions
4. **essay** - Long text responses

### User Permissions (Admin)
- `isCanCreate` - Create new forms
- `isCanDelete` - Delete forms/responses
- `isCanUpdate` - Edit existing forms
- `isCanRead` - View forms and data
- `isCanGetAnalysis` - Access statistics

---

**Created**: 2025-11-28  
**Developer**: Mohamed Fouad Bala
