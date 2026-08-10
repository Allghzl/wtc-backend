<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Track;
use App\Models\Module;
use Illuminate\Support\Str;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates 6-8 modules per track for comprehensive coverage.
     * Total: ~70 modules across 10 tracks.
     */
    public function run(): void
    {
        $modulesData = [
            // Track 1: Web Development Fundamentals (7 modules)
            'web-dev-fundamentals' => [
                ['title' => 'Introduction to Web Development', 'order' => 1],
                ['title' => 'HTML Fundamentals', 'order' => 2],
                ['title' => 'CSS Styling & Layouts', 'order' => 3],
                ['title' => 'CSS Flexbox & Grid', 'order' => 4],
                ['title' => 'JavaScript Basics', 'order' => 5],
                ['title' => 'DOM Manipulation', 'order' => 6],
                ['title' => 'Responsive Web Design', 'order' => 7],
            ],

            // Track 2: Frontend Development with React (8 modules)
            'frontend-react' => [
                ['title' => 'React Introduction & Setup', 'order' => 1],
                ['title' => 'JSX & Components', 'order' => 2],
                ['title' => 'Props & Component Communication', 'order' => 3],
                ['title' => 'State Management Basics', 'order' => 4],
                ['title' => 'React Hooks (useState, useEffect)', 'order' => 5],
                ['title' => 'React Router & Navigation', 'order' => 6],
                ['title' => 'Forms & User Input', 'order' => 7],
                ['title' => 'API Integration with React', 'order' => 8],
            ],

            // Track 3: Backend Development with Laravel (8 modules)
            'backend-laravel' => [
                ['title' => 'Laravel Installation & Configuration', 'order' => 1],
                ['title' => 'Routing & Controllers', 'order' => 2],
                ['title' => 'Blade Templates', 'order' => 3],
                ['title' => 'Eloquent ORM Basics', 'order' => 4],
                ['title' => 'Database Migrations & Seeding', 'order' => 5],
                ['title' => 'Authentication & Authorization', 'order' => 6],
                ['title' => 'RESTful API Development', 'order' => 7],
                ['title' => 'API Resources & Validation', 'order' => 8],
            ],

            // Track 4: Database Design & SQL (7 modules)
            'database-sql' => [
                ['title' => 'Database Fundamentals', 'order' => 1],
                ['title' => 'Relational Database Concepts', 'order' => 2],
                ['title' => 'SQL Query Basics (SELECT, WHERE)', 'order' => 3],
                ['title' => 'SQL Joins & Relationships', 'order' => 4],
                ['title' => 'Database Design & Normalization', 'order' => 5],
                ['title' => 'Indexes & Query Optimization', 'order' => 6],
                ['title' => 'Transactions & Data Integrity', 'order' => 7],
            ],

            // Track 5: API Development & Integration (7 modules)
            'api-development' => [
                ['title' => 'REST API Principles', 'order' => 1],
                ['title' => 'HTTP Methods & Status Codes', 'order' => 2],
                ['title' => 'API Authentication (Token, JWT)', 'order' => 3],
                ['title' => 'API Versioning & Documentation', 'order' => 4],
                ['title' => 'Consuming External APIs', 'order' => 5],
                ['title' => 'Error Handling & Validation', 'order' => 6],
                ['title' => 'API Rate Limiting & Caching', 'order' => 7],
            ],

            // Track 6: Modern JavaScript & TypeScript (8 modules)
            'javascript-typescript' => [
                ['title' => 'Modern JavaScript (ES6+)', 'order' => 1],
                ['title' => 'Arrow Functions & Destructuring', 'order' => 2],
                ['title' => 'Promises & Async/Await', 'order' => 3],
                ['title' => 'JavaScript Modules', 'order' => 4],
                ['title' => 'TypeScript Fundamentals', 'order' => 5],
                ['title' => 'TypeScript Types & Interfaces', 'order' => 6],
                ['title' => 'TypeScript with React', 'order' => 7],
                ['title' => 'Advanced TypeScript Patterns', 'order' => 8],
            ],

            // Track 7: Web Security Fundamentals (7 modules)
            'web-security' => [
                ['title' => 'Web Security Introduction', 'order' => 1],
                ['title' => 'Authentication vs Authorization', 'order' => 2],
                ['title' => 'OWASP Top 10 Vulnerabilities', 'order' => 3],
                ['title' => 'SQL Injection Prevention', 'order' => 4],
                ['title' => 'XSS & CSRF Protection', 'order' => 5],
                ['title' => 'Secure Password Storage', 'order' => 6],
                ['title' => 'HTTPS & SSL/TLS', 'order' => 7],
            ],

            // Track 8: DevOps & Deployment (7 modules)
            'devops-deployment' => [
                ['title' => 'Version Control with Git', 'order' => 1],
                ['title' => 'Git Branching & Merging', 'order' => 2],
                ['title' => 'Docker Fundamentals', 'order' => 3],
                ['title' => 'Docker Compose', 'order' => 4],
                ['title' => 'CI/CD Basics', 'order' => 5],
                ['title' => 'Deployment Strategies', 'order' => 6],
                ['title' => 'Monitoring & Logging', 'order' => 7],
            ],

            // Track 9: UI/UX for Web Developers (6 modules)
            'ui-ux-web' => [
                ['title' => 'UI/UX Design Principles', 'order' => 1],
                ['title' => 'User Research & Personas', 'order' => 2],
                ['title' => 'Wireframing & Prototyping', 'order' => 3],
                ['title' => 'Color Theory & Typography', 'order' => 4],
                ['title' => 'Accessibility (A11y) Standards', 'order' => 5],
                ['title' => 'Responsive Design Patterns', 'order' => 6],
            ],

            // Track 10: Full Stack Project Development (7 modules)
            'fullstack-project' => [
                ['title' => 'Project Planning & Requirements', 'order' => 1],
                ['title' => 'Database Schema Design', 'order' => 2],
                ['title' => 'Backend API Development', 'order' => 3],
                ['title' => 'Frontend Implementation', 'order' => 4],
                ['title' => 'Authentication & User Management', 'order' => 5],
                ['title' => 'Testing & Quality Assurance', 'order' => 6],
                ['title' => 'Deployment & Production', 'order' => 7],
            ],
        ];

        $totalModules = 0;

        foreach ($modulesData as $trackSlug => $modules) {
            $track = Track::where('slug', $trackSlug)->first();

            if (!$track) {
                $this->command->warn("⚠️  Track not found: {$trackSlug}");
                continue;
            }

            foreach ($modules as $moduleData) {
                Module::updateOrCreate(
                    [
                        'track_id' => $track->id,
                        'slug' => Str::slug($moduleData['title']),
                    ],
                    [
                        'title' => $moduleData['title'],
                        'order' => $moduleData['order'],
                    ]
                );
                $totalModules++;
            }
        }

        $this->command->info("✅ Modules seeded: {$totalModules}");
        $this->command->info("   📚 Distribution: ~6-8 modules per track across 10 tracks");
    }
}
