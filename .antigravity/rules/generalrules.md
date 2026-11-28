# IntercityWheels Autonomous Build Rules

1. **Source of Truth:** Always follow `INTERCITYWHEELS_PRD.md` for all database schemas, business rules, fare formulas, and workflows.
2. **State Tracking:** Maintain a `PROGRESS.md` file in the root directory. After completing and testing every sub-task, check it off in `PROGRESS.md` before starting the next task.
3. **No Placeholders:** Never leave `// TODO`, mock JSON responses, or static UI-only screens. Implement complete backend controllers, database migrations, and frontend state wiring.
4. **Verify Before Moving On:** After each phase, run the build (`npm run build` or equivalent) and execute API/unit tests. If any error occurs, fix it immediately before advancing.
5. **Continuous Execution:** Do not stop to ask for confirmation between phases. Proceed autonomously until all phases in `PROGRESS.md` are marked `[x]` and the production build passes with zero errors.
