-- Example rendered by DatabaseSchemaGenerator::generateMsSqlCreateTable().
-- Inputs: hostitemid A100/A200, cora_acct_id 12/13, techniciannumber 007/0123
-- (forced string), worked_on with fractional seconds, clock_in 17:26:31 and
-- 09:01:02, amount 12345.67/1.2 as DECIMAL, note a\b.
-- Flags: schema C208384, primary key hostitemid + cora_acct_id, timestamps,
-- collation SQL_Latin1_General_CP1_CI_AS.

IF OBJECT_ID(N'[C208384].[TechPayroll]', N'U') IS NULL
BEGIN
    CREATE TABLE [C208384].[TechPayroll] (
        [hostitemid] NVARCHAR(255) COLLATE SQL_Latin1_General_CP1_CI_AS NOT NULL,
        [cora_acct_id] INT NOT NULL,
        [techniciannumber] NVARCHAR(255) COLLATE SQL_Latin1_General_CP1_CI_AS NULL,
        [worked_on] DATETIME2 NULL,
        [clock_in] TIME NULL,
        [amount] DECIMAL(7,2) NULL,
        [note] NVARCHAR(255) COLLATE SQL_Latin1_General_CP1_CI_AS NULL,
        [created_at] DATETIME NULL,
        [updated_at] DATETIME NULL,
        CONSTRAINT [PK_C208384_TechPayroll] PRIMARY KEY NONCLUSTERED ([hostitemid], [cora_acct_id])
    );
END
