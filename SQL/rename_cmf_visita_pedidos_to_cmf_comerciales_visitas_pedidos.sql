USE [integral];
GO

IF OBJECT_ID(N'[dbo].[cmf_visita_pedidos]', N'U') IS NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_visita_pedidos] no existe.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'[dbo].[cmf_comerciales_visitas_pedidos]', N'U') IS NOT NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_comerciales_visitas_pedidos] ya existe.', 16, 1);
    RETURN;
END;
GO

EXEC sp_rename
    @objname = N'dbo.cmf_visita_pedidos',
    @newname = N'cmf_comerciales_visitas_pedidos';
GO

SELECT
    old_table_exists = OBJECT_ID(N'[dbo].[cmf_visita_pedidos]', N'U'),
    new_table_exists = OBJECT_ID(N'[dbo].[cmf_comerciales_visitas_pedidos]', N'U');
GO
