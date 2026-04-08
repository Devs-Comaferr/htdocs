USE [integral];
GO

IF OBJECT_ID(N'[dbo].[cmf_comerciales_calendario_agenda]', N'U') IS NOT NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_comerciales_calendario_agenda] ya existe.', 16, 1);
    RETURN;
END;
GO

CREATE TABLE [dbo].[cmf_comerciales_calendario_agenda]
(
    [id] INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [cod_vendedor] INT NOT NULL,
    [fecha] DATE NOT NULL,
    [hora_inicio] TIME(0) NULL,
    [hora_fin] TIME(0) NULL,
    [tipo_evento] VARCHAR(50) NULL,
    [descripcion] VARCHAR(255) NULL
);
GO

CREATE INDEX [idx_cal_agenda_vendedor_fecha]
ON [dbo].[cmf_comerciales_calendario_agenda] ([cod_vendedor], [fecha]);
GO

CREATE INDEX [idx_cal_agenda_fecha]
ON [dbo].[cmf_comerciales_calendario_agenda] ([fecha]);
GO

SELECT
    table_exists = OBJECT_ID(N'[dbo].[cmf_comerciales_calendario_agenda]', N'U'),
    idx_cal_agenda_vendedor_fecha = INDEXPROPERTY(OBJECT_ID(N'[dbo].[cmf_comerciales_calendario_agenda]', N'U'), N'idx_cal_agenda_vendedor_fecha', 'IndexId'),
    idx_cal_agenda_fecha = INDEXPROPERTY(OBJECT_ID(N'[dbo].[cmf_comerciales_calendario_agenda]', N'U'), N'idx_cal_agenda_fecha', 'IndexId');
GO
