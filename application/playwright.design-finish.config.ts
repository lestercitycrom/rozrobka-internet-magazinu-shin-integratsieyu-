import { defineConfig } from '@playwright/test';
import filters from './playwright.filters.config';

// Reuse the reviewed hermetic snapshot fixture, never a live commerce target.
if (process.env.FILTERS_LIVE_URL) throw new Error('Design journey mutations are local-only');

export default defineConfig({
    ...filters,
    testMatch: /design-finish\.spec\.ts/,
    outputDir: './tmp/design-finish-tests',
    projects: filters.projects?.map(project => ({ ...project, name: project.name!.replace('filters-', 'design-') })),
});
