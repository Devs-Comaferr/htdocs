USE [integral];
GO

IF OBJECT_ID(N'[dbo].[cmf_asignacion_zonas_clientes]', N'U') IS NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_asignacion_zonas_clientes] no existe.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'[dbo].[cmf_comerciales_clientes_zona]', N'U') IS NOT NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_comerciales_clientes_zona] ya existe.', 16, 1);
    RETURN;
END;
GO

EXEC sp_rename
    @objname = N'dbo.cmf_asignacion_zonas_clientes',
    @newname = N'cmf_comerciales_clientes_zona';
GO

SELECT
    old_table_exists = OBJECT_ID(N'[dbo].[cmf_asignacion_zonas_clientes]', N'U'),
    new_table_exists = OBJECT_ID(N'[dbo].[cmf_comerciales_clientes_zona]', N'U');
GO
