import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import tseslint from 'typescript-eslint'
import { defineConfig, globalIgnores } from 'eslint/config'

export default defineConfig([
  globalIgnores(['dist']),
  {
    files: ['**/*.{ts,tsx}'],
    extends: [
      js.configs.recommended,
      tseslint.configs.recommended,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
    languageOptions: {
      globals: globals.browser,
    },
    rules: {
      // An effect may only return a cleanup function. `useEffect(() => window.scrollTo(0, 0))` returns a
      // Promise in current Chromium and crashes React, so effects must always use a block body.
      'no-restricted-syntax': ['error', {
        selector: 'CallExpression[callee.name=/^use(Layout|Insertion)?Effect$/] > ArrowFunctionExpression[expression=true]',
        message: 'Use a block body in effects: `useEffect(() => { doThing() }, deps)`. Returning a value crashes React.',
      }],
    },
  },
  {
    // Route table and entry point are configuration, not hot-reloaded component modules.
    files: ['src/router.tsx', 'src/main.tsx'],
    rules: { 'react-refresh/only-export-components': 'off' },
  },
])
