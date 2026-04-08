USE [integral];
GO

IF OBJECT_ID(N'[dbo].[cmf_dias_no_laborables]', N'U') IS NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_dias_no_laborables] no existe.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'[dbo].[cmf_comerciales_dias_no_laborables]', N'U') IS NOT NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_comerciales_dias_no_laborables] ya existe.', 16, 1);
    RETURN;
END;
GO

EXEC sp_rename
    @objname = N'dbo.cmf_dias_no_laborables',
    @newname = N'cmf_comerciales_dias_no_laborables';
GO

SELECT
    old_table_exists = OBJECT_ID(N'[dbo].[cmf_dias_no_laborables]', N'U'),
    new_table_exists = OBJECT_ID(N'[dbo].[cmf_comerciales_dias_no_laborables]', N'U');
GO
