USE [integral];
GO

IF OBJECT_ID(N'[dbo].[cmf_configuracion_app]', N'U') IS NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_configuracion_app] no existe.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'[dbo].[cmf_comerciales_app_config]', N'U') IS NOT NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_comerciales_app_config] ya existe.', 16, 1);
    RETURN;
END;
GO

EXEC sp_rename
    @objname = N'dbo.cmf_configuracion_app',
    @newname = N'cmf_comerciales_app_config';
GO

SELECT
    old_table_exists = OBJECT_ID(N'[dbo].[cmf_configuracion_app]', N'U'),
    new_table_exists = OBJECT_ID(N'[dbo].[cmf_comerciales_app_config]', N'U');
GO
