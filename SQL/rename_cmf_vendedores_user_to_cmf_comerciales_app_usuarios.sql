USE [integral];
GO

IF OBJECT_ID(N'[dbo].[cmf_vendedores_user]', N'U') IS NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_vendedores_user] no existe.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'[dbo].[cmf_comerciales_app_usuarios]', N'U') IS NOT NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_comerciales_app_usuarios] ya existe.', 16, 1);
    RETURN;
END;
GO

EXEC sp_rename
    @objname = N'dbo.cmf_vendedores_user',
    @newname = N'cmf_comerciales_app_usuarios';
GO

SELECT
    old_table_exists = OBJECT_ID(N'[dbo].[cmf_vendedores_user]', N'U'),
    new_table_exists = OBJECT_ID(N'[dbo].[cmf_comerciales_app_usuarios]', N'U');
GO
