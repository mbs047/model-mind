# Database Tables

ModelMind uses one migration file and creates three tables:

- `model_mind_sessions`
- `model_mind_messages`
- `model_mind_memories`

## Table Prefix

Change the table prefix before running migrations:

```env
MODEL_MIND_TABLE_PREFIX=assistant_
```

With that setting, the tables become:

- `assistant_sessions`
- `assistant_messages`
- `assistant_memories`

Set the prefix before the first migration run. If tables already exist, create an application migration to rename them safely.
