USE [integral];
GO

IF OBJECT_ID(N'[dbo].[cmf_visitas_comerciales]', N'U') IS NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_visitas_comerciales] no existe.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'[dbo].[cmf_comerciales_visitas]', N'U') IS NOT NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_comerciales_visitas] ya existe.', 16, 1);
    RETURN;
END;
GO

EXEC sp_rename
    @objname = N'dbo.cmf_visitas_comerciales',
    @newname = N'cmf_comerciales_visitas';
GO

SELECT
    old_table_exists = OBJECT_ID(N'[dbo].[cmf_visitas_comerciales]', N'U'),
    new_table_exists = OBJECT_ID(N'[dbo].[cmf_comerciales_visitas]', N'U');
GO
