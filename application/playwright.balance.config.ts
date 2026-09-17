import { defineConfig } from '@playwright/test';
import classicPreview from './playwright.classic.config';

// Keep the hermetic two-profile fixture (including its guarded SQLite database,
// disabled integrations and opt-in local autostart) as the single source of
// preview-server policy.  This config only expands its visual acceptance matrix.
const inheritedProjects = classicPreview.projects ?? [];
const profiles = ['modern', 'classic'] as const;
const locales = ['nb', 'ru'] as const;
const widths = [1440, 1280, 900, 390, 320] as const;

const profileTemplate = (profile: typeof profiles[number]) => {
    const template = inheritedProjects.find(project => project.name === `${profile}-nb-desktop`);
    if (!template) throw new Error(`The classic preview config is missing its ${profile} desktop fixture.`);
    return template;
};

export default defineConfig({
    ...classicPreview,
    testMatch: /customer-design-balance\.spec\.ts/,
    outputDir: './tmp/customer-balance/test-results',
    reporter: [
        ['list'],
        ['html', { outputFolder: './tmp/customer-balance/report', open: 'never' }],
    ],
    projects: profiles.flatMap(profile => locales.flatMap(locale => widths.map(width => {
        const template = profileTemplate(profile);
        const mobile = width < 768;
        return {
            ...template,
            name: `balance-${profile}-${locale}-${width}`,
            metadata: { ...template.metadata, designProfile: profile, locale, balanceWidth: width },
            use: {
                ...template.use,
                viewport: { width, height: width >= 768 ? 900 : (width === 390 ? 844 : 800) },
                isMobile: mobile,
                hasTouch: mobile,
            },
        };
    }))),
});
