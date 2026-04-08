USE [integral];
GO

IF OBJECT_ID(N'[dbo].[cmf_zonas_rutas]', N'U') IS NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_zonas_rutas] no existe.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'[dbo].[cmf_comerciales_clientes_zona_rutas]', N'U') IS NOT NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_comerciales_clientes_zona_rutas] ya existe.', 16, 1);
    RETURN;
END;
GO

EXEC sp_rename
    @objname = N'dbo.cmf_zonas_rutas',
    @newname = N'cmf_comerciales_clientes_zona_rutas';
GO

SELECT
    old_table_exists = OBJECT_ID(N'[dbo].[cmf_zonas_rutas]', N'U'),
    new_table_exists = OBJECT_ID(N'[dbo].[cmf_comerciales_clientes_zona_rutas]', N'U');
GO
