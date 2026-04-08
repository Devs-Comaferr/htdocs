USE [integral];
GO

IF OBJECT_ID(N'[dbo].[cmf_zonas_visita]', N'U') IS NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_zonas_visita] no existe.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'[dbo].[cmf_comerciales_zonas]', N'U') IS NOT NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_comerciales_zonas] ya existe.', 16, 1);
    RETURN;
END;
GO

EXEC sp_rename
    @objname = N'dbo.cmf_zonas_visita',
    @newname = N'cmf_comerciales_zonas';
GO

SELECT
    old_table_exists = OBJECT_ID(N'[dbo].[cmf_zonas_visita]', N'U'),
    new_table_exists = OBJECT_ID(N'[dbo].[cmf_comerciales_zonas]', N'U');
GO
